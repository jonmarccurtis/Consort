<?php
/**
 * User: joncu
 * Date: 3/3/20
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

/**
 * Class CuCalendarSubscription
 *
 * Supports both a page where users can subscript to various CC Calendars
 * and a callback that filters Event Manager's /events.ics URL
 *
 * This used to depend on s2member user levels.  It also used the current
 * user ID for nonces, but was also able to function with no user ID when
 * no one was logged in.  That is almost always the case now, so the code
 * that uses the user_id is left intact.
 */
class CuCalendarSubscription
{
    /**
     * @var array - Calendar Event Categories
     *
     * Key = bitfield values
     * filter = what categories are sent with the request
     *
     */
    static private $options = array(
        array(
            'type' => 'section',
            'label' => 'Select types of events to include:'
        ),
        array(
            'type' => 'category',
            'slug' => 'consort',
            'label' => 'Consort Public Events (<em>Fundraisers, Annual Concert</em>)',
            'cat_fld' => 0b00000001,
            'cat_id' => '10'
        ),
        array(
            'type' => 'category',
            'slug' => 'other',
            'label' => 'Other Group\'s Events (<em>Concerts, Workshops</em>)',
            'cat_fld' => 0b00000010,
            'cat_id' => '7'
        ),
        array(
            'type' => 'category',
            'slug' => 'singer',
            'label' => 'Singer/Season Events (<em>Rehearsals</em>)',
            'cat_fld' => 0b00000100,
            'cat_id' => '8'
        ),
        array(
            'type' => 'section',
            'label' => 'Additional setting:'
        ),
        array(
            'type' => 'setting',
            'slug' => 'future_only',
            'label' => 'Show only future events (<em>remove from your calendar when passed</em>)'
        ),
    );

    // Note - only room for two more settings and 1 more cat (used to have 'board'), to stay within 2 hex bytes
    static private $settings = array(
        'future_only' => 0b00010000,
        'category_mask' => 0b00001111,
        'default' => 0b10000000  // keeps opts as a 2 byte field
    );

    public function __construct()
    {
    }

    public function html() {

        $html = '';
        $opts = self::$settings['default'];
        foreach (self::$options as $key => $option) {
            if ($option['type'] == 'section') {
                    $html .= '<br><div><b>' . $option['label'] . '</b></div>';
                } else {
                    $bit = ($option['type'] == 'category') ? $option['cat_fld'] : self::$settings[$option['slug']];
                    $checked = ' checked="checked"'; // Used to have one option default to not checked
                    $opts += $bit;

                    $elem_id = 'cal_' . $bit;
                    $html .= '<label for="' . $elem_id . '">';
                    $html .= '<input type="checkbox" name="' . $elem_id . '" class="ss-option"' . $checked . '> ';
                    $html .= $option['label'] . '</label>';
                }
            }

        $html .= '<br><div><b>Click to copy this URL, then paste it into your Calendar tool:</b></div>';
        $url = $this->get_url(get_current_user_id(), $opts);
        $html .= '<input type="text" id="opt-url" value="' . $url . '" readonly 
            title="Click to copy for your calendar tool">';
        $html .= '<div id="copied-msg">&nbsp;</div><br>';


        $html .= $this->getJS();
        return $html;
    }

    private function getJS() {
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[';

        $js .= '
        function cu_js($) {
            $(document).ready(function() {
                $("#opt-url").on("click", function() {
                    $(this)[0].select();
                    document.execCommand("copy");
                    $("#copied-msg").html("copied");
                });
                
                $(".ss-option").on("change", function() {
                    var opts = ' . self::$settings['default'] . ';
                    $(".ss-option").each(function() {
                        if ($(this).prop("checked"))
                            opts += parseInt($(this).prop("name").substring(4));
                    });
                    $("#copied-msg").html("&nbsp;");
                    if ((opts & ' . self::$settings['category_mask'] . ') == 0) {
                        $("#opt-url").val("Please select at least one type of event.");
                    } else {
                        $("#opt-url").val(" ... creating new URL ...");
                        var data = {
                            action: "cu_get_ical_url",
                            _ajax_nonce: "' . wp_create_nonce('cu_get_ical_url-' . get_current_user_id()) . '",
                            opts: opts 
                        };
                        $.post("' . admin_url('admin-ajax.php') . '", data, cu_js.response);
                    }
                });
            });
            function response(res) {
                $("#opt-url").val(res.data);
            }
            cu_js.response = response;
        }
        cu_js(jQuery);
        ';

        return $js.'
        // ]]></script>';
    }

    // AJAX callback
    public function get_ical_url() {
        $id = get_current_user_id();
        check_ajax_referer('cu_get_ical_url-' . $id);

        if (!isset($_POST['opts']))
            wp_send_json_error('Error 125: notify administrator');
        $opts = $_POST['opts'];

        wp_send_json_success($this->get_url($id, $opts));
    }

    private function get_url($id, $opts) {
        return get_site_url() . '/events.ics?opts=' . $this->encode_token($id, $opts);
    }

    public function filter_ical($args) {
        // At this time, EM only uses this callback for requesting the ICS file.
        // Make sure that is still the case - as we do not want to filter any
        // other types of calendar event requests.
        if (!preg_match('/events.ics(\?.+)?$/', $_SERVER['REQUEST_URI']))
            return $args;

        // Since there is no event category 1, this setting will return an empty calendar
        // This prevents someone from calling events.ics with no parameters and getting
        // the full calendar.  It is also the default if we fail here ...
        $args['category'] = '1';

        // There used to be a Board category, and we could get older tokens that include
        // it - but that's ok, as that category will now always return no events.
        // That is also true of tokens that have the "registered singer" option.
        // Older tokens with that option are harmless.

        // Note: EM processes this during its init(), so it never gets to the
        // WP public query_vars, so we have to do our own processing.
        if (!isset($_GET['opts']))
            return $args;

        // decode_token has protections, knowing the token comes from a URL
        $pars = $this->decode_token($_GET['opts']);
        if ($pars === false)
            return $args;

        $ver = $pars['ver'];
        if ($ver == 'A') {
            // Handle Tokens of Version A

            $id = $pars['id'];
            $opts = $pars['opts'];

            if (($opts & self::$settings['category_mask']) == 0)  // No Categories selected == $opt);
                return $args;

            $user = null;
            if ($id > 0) { // this is for a member
                $user = get_user_by('id', $id);
                if ($user === false)
                    return $args;  // ID is invalid
            }

            $future_only = ($opts & self::$settings['future_only']) == self::$settings['future_only'];
            if (!$future_only)  // This is Event Manager's default
                $args['scope'] = 'all';

            // Now make sure the user has access to the requested calendars (no longer necessary)
            $cats = array();
            foreach (self::$options as $option) {
                if ($option['type'] != 'category')
                    continue;

                if (($opts & $option['cat_fld']) == $option['cat_fld']) {
                    // Has requested this event category

                    // There used to be a lot of logic here, to filter based on the s2member user
                    // level or whether they are registered for the current season.
                    // This is no longer needed, as anyone can request any cat.
                    $cats[] = $option['cat_id'];
                }
            }
            if (count($cats) == 0)
                return $args;

            $args['category'] = implode(',', $cats);
            return $args;

        } else {
            // Unsupported version
            return $args;
        }
    }

    /**
     * @param $id - user_id 1 < 500
     * @param $opts - bit field of options 0 < 0b10000000 (0xff)
     * @return string - token for URL query
     *
     * The token is:
     *     1st char = version code
     *     2nd char = base/key value for decoding (hex)
     *     pars is encoded:
     *         1st 2 chars = options bit field (hex)
     *         user_id
     */
    private function encode_token($id, $opts) {
        static $cur_ver = 'A';

        $x_id = base_convert($id, 10, 16);
        $x_opts = base_convert($opts, 10, 16); // 2 chars

        $pars = $x_opts . $x_id;

        $base = rand(2, 17);
        $enc_pars = urlencode(base64_encode(base_convert($pars, 16, $base)));
        $key = $base - 2;
        $x_key = base_convert($key, 10, 16); // should be single digit

        $token = $cur_ver . $x_key . $enc_pars;
        return $token;
    }

    /**
     * @param $token - encoded URL query parameter
     * @return array|bool - user_id and calendar bit field
     *    Since this is working off a passed in value, returns false on error
     *
     * This should be the exact reverse of encode above
     */
    private function decode_token($token) {
        // Using try/catch because $token comes from URL
        try {
            $ver = $token[0];
            if ($ver == 'A') {
                $x_key = $token[1];
                $enc_pars = substr($token, 2);

                $key = base_convert($x_key, 16, 10);
                $base = $key + 2;
                $pars = base_convert(base64_decode(urldecode($enc_pars)), $base, 16);

                $x_opts = substr($pars, 0, 2);
                $x_id = substr($pars, 2);

                $opts = base_convert($x_opts, 16, 10);
                $id = base_convert($x_id, 16, 10);

                return array('ver' => $ver, 'id' => $id, 'opts' => $opts);
            } else {
                return false;  // no other versions, yet
            }
        }
        catch (Exception $e) {
            return false;
        }
    }
}
