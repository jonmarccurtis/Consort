<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class CuWsLunchList
{
    private $id;

    public function __construct($atts)
    {
        if (isset($atts['id']))
            $this->id = $atts['id'];
        else
            $this->id = null;
    }

    public function html()
    {
        if ($this->id == null)
            return '<p>ERROR: missing Caldera Form ID</p>';

        $html = "<b>Lunch Signup List:</b>";

        require_once( CFCORE_PATH . 'classes/admin.php' );
        $data = Caldera_Forms_Admin::get_entries($this->id, 1, 100);
        foreach($data['entries'] as $entry) {
            $html .= '<br>' . $entry['data']['hid_name'];
        }

        $html .= "<br><em>If you need to cancel, contact 
            <span id='lunch_can' class='send-adr' title='Send email'>Lucinda Ray</span></em>";


        $html .= $this->getJS();
        return $html;
    }

    private function getJS()
    {
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[';

        $js .= '
        function cu_wsll($) {
            $(document).ready(function () {
                $("#lunch_can").on("click", function() {
                    var e1 = "mai";
                    var e2 = "lto:luc";
                    var e3 = "indaray@a";
                    var e4 = "ol.c";
                    var e5 = "om";
                    window.location.href = e1 + e2 + e3 + e4 + e5;
                });
            });
        }
        cu_wsll(jQuery);
        ';

        return $js.'
        // ]]></script>';
    }

}
