<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class CuSoloAuditions
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

        $html = "<b>Soloists Signup List:</b><br>";

        $auditions = array(
            'PURCELL' => array(
                'B' => array()
            ),
            'HANDEL' => array(
                'S' => array(),
                'A' => array(),
                'T' => array(),
                'B' => array()
            ),
            'RVW' => array(
                'S' => array(),
                'A' => array(),
                'T' => array(),
                'B' => array()
            ),
            'DOLE' => array(
                'S' => array()
            ),
            'BRITTEN' => array(
                'S' => array(),
                'A' => array(),
                'T' => array(),
                'B' => array()
            ),
        );

        require_once( CFCORE_PATH . 'classes/admin.php' );
        $data = Caldera_Forms_Admin::get_entries($this->id, 1, 100);
        foreach($data['entries'] as $entry) {
            $user_id = $entry['user']['ID'];
            $name = $entry['user']['name'];
            $vp = get_user_field('voice', $user_id)[0];

            foreach($auditions as $song => $solos) {
                $checked = $entry['data'][strtolower($song)];
                if ($checked == 'X') {
                    if (isset($auditions[$song][$vp]))
                    $auditions[$song][$vp][] = $name;
                }
            }
        }

        foreach($auditions as $song => $solos) {
            $html .= $song . '<br>';
            foreach($solos as $vps => $soloists) {
                $html .= '&nbsp;&nbsp;&nbsp;&nbsp;' . $vps . ' - ';
                $html .= implode(', ', $soloists) . '<br>';
            }
        }

        $html .= "<br><em>If you need to make a change, contact 
            <span id='lunch_can' class='send-adr' title='Send email'>Jon C</span></em>";


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
                    var e2 = "lto:jmc";
                    var e3 = "2024@ou";
                    var e4 = "tlook.c";
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
