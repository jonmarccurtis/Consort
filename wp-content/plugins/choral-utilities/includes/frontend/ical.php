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
 */
class CuCalendarSubscription
{
    /**
     * @var array - Calendar Event Categories
     *
     * Key = bitfield values
     * show = who can select the category for their request token
     * filter = what categories are sent with the request
     *
     * The reason for this difference is that Members can choose to
     * include Singer events, but will not see them unless they are
     * active Singers in the Current Season.  (That way, Members will
     * not need to get a new token once they register.)
     */
    static private $options = array(
        array(
            'type' => 'section',
            'label' => 'Select types of events to include:',
            'show' => null
        ),
        array(
            'type' => 'category',
            'slug' => 'consort',
            'label' => 'Consort Public Events (<em>Fundraisers, Annual Concert</em>)',
            'cat_fld' => 0b00000001,
            'cat_id' => '10',
            'show' => null,  // null = anyone can access
            'filter' => null
        ),
        array(
            'type' => 'category',
            'slug' => 'other',
            'label' => 'Other Group\'s Events (<em>Concerts, Workshops</em>)',
            'cat_fld' => 0b00000010,
            'cat_id' => '7',
            'show' => null,
            'filter' => null
        ),
        array(
            'type' => 'category',
            'slug' => 'singer',
            'label' => 'Singer/Season Events (<em>Rehearsals</em>)',
            'cat_fld' => 0b00000100,
            'cat_id' => '8',
            'show' => 'access_s2member_level1',
            'filter' => 'access_s2member_level2'
        ),
        array(
            'type' => 'category',
            'slug' => 'board',
            'label' => 'Board only Events (<em>Meetings</em>)',
            'cat_fld' => 0b00001000,
            'cat_id' => '12',
            'show' => 'access_s2member_level4',
            'filter' => 'access_s2member_level4'
        ),
        array(
            'type' => 'section',
            'label' => 'Additional settings:',
            'show' => null,
        ),
        array(
            'type' => 'setting',
            'slug' => 'future_only',
            'label' => 'Show only future events (<em>remove from your calendar when passed</em>)',
            'show' => null,
        ),
        array(
            'type' => 'setting',
            'slug' => 'registered_only',
            'label' => 'Only show Singer events if registered (<em>hide rehearsal schedule unless registered</em>)',
            'show' => 'access_s2member_level1',
        ),
    );

    // Note - only room for one more setting, to stay within 2 hex bytes
    static private $settings = array(
        'future_only' => 0b00010000,
        'registered_only' => 0b00100000,
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
            if ($option['show'] === null || current_user_can($option['show'])) {
                if ($option['type'] == 'section') {
                    $html .= '<br><div><b>' . $option['label'] . '</b></div>';
                } else {
                    $bit = ($option['type'] == 'category') ? $option['cat_fld'] : self::$settings[$option['slug']];
                    $checked = ($option['slug'] == 'registered_only') ? '' : ' checked="checked"';
                    if ($checked != '')
                        $opts += $bit;

                    $elem_id = 'cal_' . $bit;
                    $html .= '<label for="' . $elem_id . '">';
                    $html .= '<input type="checkbox" name="' . $elem_id . '" class="ss-option"' . $checked . '> ';
                    $html .= $option['label'] . '</label>';
                }
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
                        $("#opt-url").val("");
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

            // Can Member's see Singer Events, or must they be registered as Singers first?
            $registered_only = ($opts & self::$settings['registered_only']) == self::$settings['registered_only'];

            // Now make sure the user has access to the requested calendars
            $cats = array();
            foreach (self::$options as $option) {
                if ($option['type'] != 'category')
                    continue;

                if (($opts & $option['cat_fld']) == $option['cat_fld']) {
                    // Has requested this event category

                    if ($option['filter'] == null) {
                        // everyone can have this category
                        $cats[] = $option['cat_id'];

                    } else if ($user) { // The rest are only for members

                        $is_singer_cat = ($option['slug'] == 'singer');

                        // This is disabled.  It is a sort-of violation of the Current Season rules,
                        // but in this case - we want to let members see the rehearsal schedule as
                        // soon as it is ready, even if that is before the rest of the current
                        // season is ready to turn on.  They can still turn this off by setting
                        // 'registered_only' to true.
                        //
                        // Don't include Singer's events outside current season
                        //if ($is_singer_cat && !CurrentSeason::is_current_season())
                        //    continue;

                        // Is the user's access level currently high enough to see this category,
                        // or this is the Singer Events Category and the user indicates to always show
                        // them. In that case, we don't need to recheck the user capabilities, as
                        // the only level that doesn't include Singer events is the lowest, Member.
                        // If they are no longer a Member, then their user_id would be invalid and
                        // they would have been filtered out above.
                        if ($user->has_cap($option['filter']) || ($is_singer_cat && !$registered_only)) {
                            $cats[] = $option['cat_id'];
                        }
                    }
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
