<?php
/**
 * Created by IntelliJ IDEA.
 * User: joncu
 * Date: 4/22/19
 * Time: 1:06 PM
 */
if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class SectionWidget {
    public function __construct()
    {
        wp_enqueue_style('rn_common');
        wp_enqueue_script('rn_common');
    }

    /**
     * Note: this is duplicated in rn_common.js ...
     */
    public function html($vps, $sel = '', $id = '', $read_only = false) {
        // This is an array for convenience in editing.
        // JS cannot handle returns in strings

        $sel_list = explode(',', $sel);
        $vps_list = explode(',', $vps);
        $show_list = array();
        if ($read_only) {
            $id_prefix = 'ro-';
            $option = 'ro-option';
            $sel = ' ro-selected';
        } else {
            $id_prefix = 'vp-';
            $option = 'vp-option';
            $sel = ' vp-selected';
        }
        foreach ($vps_list as $vp) {
            $cls = in_array($vp, $sel_list) ? $sel : '';
            $show_list[] = '<span id="' . $id_prefix . $id . '-' . $vp . '" class="' . $option
                . $cls . '">' . $vp . '</span>';
        }
        return '<span>' . implode(',&shy', $show_list) . '</span>';
    }


    /**
     * Using the Singer's Primary VP and Exceptions, returns array of
     * each Song's VoiceParts for that singer.
     * @param $singer
     * @param $songs
     * @return array - song_id => singer's VPS for that song
     */
    static function singer_vps($singer, $songs) {
        $vp_exceptions = $singer['vp_exceptions'];
        $pvp = $singer['primary_vp'];
        $full_vp = ',' . $pvp . ',';
        $abbr_vp = ',' . substr($pvp, 0, 1) . ',';

        $vps = array();
        foreach ($songs as $song) {
            $song_id = $song[RN::ID];
            $song_vps = ',' . $song[RN::VP] . ',';
            // First determine the default VP
            $vp = '';
            if (!empty($pvp)) {
                if (strpos($song_vps, $full_vp) !== false)
                    $vp = $pvp;
                else if (strpos($song_vps, $abbr_vp) !== false)
                    $vp = substr($pvp, 0, 1);
            }
            // Then see if it is overridden by an exception
            if (isset($vp_exceptions[$song_id])) {
                $vp = $vp_exceptions[$song_id]['exceptions'];
            }
            $vps[$song_id] = $vp;
        }
        return $vps;
    }
}