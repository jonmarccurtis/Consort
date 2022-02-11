<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

/** Provides Analytics for Consort WP
 * Based on s2member rolls
 * Has dependency on WordFence (for data)
 * WordFence - set Live Data to be "All Traffic"
 */
class CuAnalytics
{
    private $table, $row_total;

    public function __construct()
    {
        require_once(plugin_dir_path(__FILE__).'../frontend/cu_table.php');
        $this->table = new CuTable();
        $this->table->table_class('analytics');
        $this->table->set_option('header offset', '32');
    }

    // ************* CREATE ANALYTICS PAGE **************

    public function create_page()
    {
        add_management_page(
            'Consort Analytics',
            'Consort Analytics',
            'access_s2member_level3',
            'cu-analytics-tool',
            array($this, 'render_page'));
    }

    // Render Analytics Page
    public function render_page()
    {
        $this->renderHtml();
        $this->renderJS();
    }

    private function renderHtml()
    {
        // Page Header
        echo '
<h1>Consort Website Analytics</h1>';

        $this->table->set_option('directions-left',
            '<div>Current rows showing: <span class="row_count"></span></div>');

        $this->table->add_column('name',
            array());

        $this->table->add_column('page',
            array());

        $this->table->add_column('time',
            array('width' => '100px'));

        $this->table->add_column('browser',
            array());

        $this->table->add_column('device',
            array());

        $this->table->add_column('OS',
            array('width' => '75px'));

        $data = $this->get_data();
        $row_id = 0;
        foreach($data as $user) {
            $row = array(
                'data' => $user['cols'],
                'class' => array(),
                'tr_pars' => array(
                    'title'  => 'ID = ' . $user['ext']['id'] . '&#013;UA = ' . $user['ext']['ua']
                ));
            $this->table->add_row(++$row_id, $row);
        }
        $this->row_total = $row_id;

        echo $this->table->get_html();

    }

    private function get_data() {
        global $wpdb;

        // DEPENDENCY on WordFence DATA
        $table_name = $wpdb->prefix . 'wfhits';
        $res = $wpdb->get_results("
            SELECT userID, UA, URL, ctime
            FROM $table_name
            WHERE userID > 0 AND statusCode = 200
        ", ARRAY_A);

        require_once('../wp-content/plugins/wordfence/lib/wfBrowscap.php');
        $browscap = wfBrowscap::shared();

        $data = array();
        foreach($res as $user) {
            $ud = get_userdata($user['userID']);
            $name = $ud->first_name . ' ' . $ud->last_name;
            $au = $user['UA'];
            $agent = $browscap->getBrowser($au);
            $data[] = array(
                'cols' => array(
                    'name' => $name,
                    'page' => basename(explode('?', $user['URL'])[0]),
                    'time' => date('m/d/y H:i', $user['ctime']),
                    'browser' => $agent['Browser'],
                    'device' => $agent['Device_Type'],
                    'os' => $agent['Platform'],
                ),
                'ext' => array(
                    'id' => $user['userID'],
                    'ua' => $user['UA'],
                )
            );
        }
        return $data;
    }

    private function renderJS()
    {
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[';

        $js .= '
        function cu_ans($) {
            $(document).ready(function () {
                $("#cu_table").bind("filterEnd", cu_ans.calc_rows);
                calc_rows();
            });
            
            function calc_rows() {
                var count = 0;
                var total = ' . $this->row_total . ';
                $.each($("#cu_table tr"), function() {
                    if ($(this).hasClass("tablesorter-ignoreRow"))
                        return;
                    if ($(this).hasClass("tablesorter-headerRow"))
                        return;
                    if ($(this).hasClass("filtered"))
                        return;
                    count++;
                });
                $(".row_count").html(count + "/" + total + ", " 
                    + Math.round((count/total)*100) + "%");
            }
            cu_ans.calc_rows = calc_rows;
        }
        cu_ans(jQuery);
        ';

        echo $js.'
        // ]]></script>';
    }
}
