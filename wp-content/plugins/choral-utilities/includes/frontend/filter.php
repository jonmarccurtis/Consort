<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

// CC_DEP - CuFilter only used in old menus
class CuFilter
{
    private $atts, $content;

    public function __construct($atts, $content)
    {
        $this->atts = $atts;
        $this->content = $content;
    }

    public function html()
    {
        // Currently only 'position' is supported
        if (count($this->atts) != 1 || !isset($this->atts['position']))
            return 'ERROR: Unsupported filter type(s)';

        // Handle Member's position
        $filter = $this->atts['position'];
        $positions = get_user_field('position');
        if (is_array($positions)) {
            foreach ($positions as $position) {
                if ($position == $filter)
                    return $this->content;
            }
        }
        return '';

    }
}
