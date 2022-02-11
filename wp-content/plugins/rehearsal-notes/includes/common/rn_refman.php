<?php
/**
 * Created by IntelliJ IDEA.
 * User: joncu
 * Date: 3/31/20
 * Time: 5:39 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

/**
 * Class RnReferenceManual
 *
 * Each topic in the manual is kept in separate HTML files under assets/man.
 * This is so that each topic can either be included in a manual, or by itself
 * within the page it pertains to, or from a link on that page.
 *
 * The HTML files are edited in BlueGriffin and include similar CSS links so that
 * they are formatted correctly in BlueGriffin.  When used on the live site,
 * their <body> section is extracted, so the CSS links are not used.
 *
 * An exception to topics in separate HTML files, are instructions pertaining
 * to the manual itself, which is in this file.
 *
 * The manual is automatically generated from the files using the $table array,
 * and can be filtered by the RN roles.
 *
 * WP usage via a shortcode ...
 *     [rn_refman] = only used on the rn-refman page. The manual type is passed in a URL query par, man=.
 *     [rn_refman man="manual type"] = can be used anywhere
 *     [rn_refman man="manual type" rm_link="_self|_blank"] = include default link to full Reference Manual (default = "")
 *
 *   "manual type" (or URL query par man=) ...
 *     <slug> = which is also the filename of the topic's HTML file, as defined in the $table array
 *     rm-snda = full reference manual for Singer, NT, Dir, and/or Admin
 *
 * Internal links are primarily to other topics.  When the topic is not included in a
 * generated manual, the links are translated to external links.  Links within a topic
 * should use the form topic#link.  They are translated to normal internal links
 * when the topic HTML is generated.
 *
 * In addition to the main $table, there are some "segments".  These are small files which contain content
 * that is needed in more than one topic.  These are embedded using a local shortcode [file-name].
 */
class RnReferenceManual
{
    private $table, $segs, $rctx, $crtx, $html, $html_toc, $html_content, $toc_subtopic, $access, $def_man, $site_url;

    public function __construct()
    {
        // This is for testing purposes.  When true = test server links will point back to test server.
        // When false = all links will be the live server, meaning that PDFs created on a test server
        // will still be valid.
        $testing_on = false;
        $this->site_url = $testing_on ? get_site_url() : 'https://www.consortchorale.org';

        wp_enqueue_style('rn_common');
        wp_enqueue_style('rn_frontend');
        wp_enqueue_style('genericons');

        // Set what the user has access to, and the default reference manual link
        require_once(plugin_dir_path(__FILE__).'/../common/rn_database.php');
        $this->singer = RnSingersDB::get_singer(get_current_user_id());
        if ($this->singer['is_admin']) {
            $this->access = 0b1111;
            $this->def_man = 'snda';
        } else if ($this->singer['is_dir']) {
            $this->access = 0b1111;
            $this->def_man = 'd';
        } else if ($this->singer['is_nt']) {
            $this->access = 0b1110;
            $this->def_man = 'n';
        } else {  // Singer
            $this->access = 0b1000;
            $this->def_man = 's';
        }

        // Role Codes define which parts to include in a full manual
        $this->rctx = array (
            's' => 0b1000,
            'n' => 0b0100,
            'd' => 0b0010,
            'a' => 0b0001
        );
        $this->crtx = array(
            0b1000 => 'Singers',
            0b0100 => 'Note Takers',
            0b0010 => 'Directors',
            0b0001 => 'Administrators'
        );

        // Only used when manual is given on its own page
        $this->html = '<style>body{background-color:white;}</style>';  // Make printable
        $this->html_toc = '';
        $this->html_content = '';
        $this->toc_subtopic = false;

        // Small segments embedded in multiple topics
        $this->segs = array(
            '[seg-vp-assign]',
            '[seg-rnote-format]'
        );
    }

    /**
     * @param $roles
     * @return int|mixed
     * Translate a string of roles (snda) into a bit field flag, rcode
     */
    private function code($roles) {
        $rc = 0b0000;
        foreach($this->rctx as $role => $code) {
            if (strpos($roles, $role) !== false)
                $rc |= $code;
        }
        return $rc;
    }


    /**
     * Table defines an outline of a full reference manual.
     * It is also the key to the set of HTML files (when type='file'), whose filename == slug
     */
    private function init_table($need_codes)
    {
        $this->table = array(
            'title' => array(
                'type' => 'title',
                'rcode' => 'snda',
                'title' => 'Rehearsal Notes Reference Manual'
            ),
            'toc' => array(
                'type' => 'toc',
                'rcode' => 'snda',
                'title' => 'Table of Contents'
            ),
            'staff-intro' => array(
                'type' => 'file',
                'rcode' => 'nda',
                'title' => 'Introduction to Rehearsal Notes'
            ),
            'navigate-tables' => array(
                'type' => 'file',
                'rcode' => 'snda',
                'title' => 'Navigating Common Tables'
            ),
            'instructions' => array(
                'type' => 'header',
                'rcode' => 'snda',
                'title' => 'Instructions'
            ),
            'singer-instruct' => array(
                'type' => 'file',
                'rcode' => 's',
                'title' => 'Instructions for Singers'
            ),
            'nt-instruct' => array(
                'type' => 'file',
                'rcode' => 'n',
                'title' => 'Instructions for Note Takers'
            ),
            'dir-instruct' => array(
                'type' => 'file',
                'rcode' => 'd',
                'title' => 'Instructions for Directors'
            ),
            'admin-instruct' => array(
                'type' => 'file',
                'rcode' => 'a',
                'title' => 'Instructions for Administrators'
            ),
            'refman' => array(
                'type' => 'header',
                'rcode' => 'snda',
                'title' => 'Topic References for Pages and Forms'
            ),
            'singer-ref-rehearsal-notes-page' => array(
                'type' => 'file',
                'rcode' => 's',
                'title' => 'Singer\'s Rehearsal Notes Page'
            ),
            'singer-ref-ask-question-form' => array(
                'type' => 'file',
                'rcode' => 's',
                'title' => 'Singer\'s Ask Question Form'
            ),
            'singer-ref-track-questions-form' => array(
                'type' => 'file',
                'rcode' => 's',
                'title' => 'Singer\'s Track Questions Form'
            ),
            'singer-ref-vp-form' => array(
                'type' => 'file',
                'rcode' => 's',
                'title' => 'Singer\'s Voice Parts Form'
            ),
            'singer-ref-vpa-page' => array(
                'type' => 'file',
                'rcode' => 's',
                'title' => 'Singer\'s Voice Parts Assignments Page'
            ),
            'nt-ref-edit-page' => array(
                'type' => 'file',
                'rcode' => 'na',
                'title' => 'Note Taker\'s Edit Rehearsal Notes Page'
            ),
            'nt-ref-add-form' => array(
                'type' => 'file',
                'rcode' => 'na',
                'title' => 'Note Taker\'s Add/Edit Notes, Ask Question Forms'
            ),
            'dir-ref-edit-page' => array(
                'type' => 'file',
                'rcode' => 'd',
                'title' => 'Director\'s Edit Rehearsal Notes Page'
            ),
            'dir-ref-edit-form' => array(
                'type' => 'file',
                'rcode' => 'd',
                'title' => 'Director\'s Edit Question, Add Note Forms'
            ),
            'admin-song-list-instruct' => array(
                'type' => 'file',
                'rcode' => '',
                'title' => 'Instructions for Settings, Song List Tab'
            ),
            'admin-singer-list-instruct' => array(
                'type' => 'file',
                'rcode' => '',
                'title' => 'Instructions for Settings, Singer List Tab'
            ),
            'admin-ref-song-list' => array(
                'type' => 'file',
                'rcode' => 'a',
                'title' => 'Settings, Song List Tab'
            ),
            'admin-ref-singer-list' => array(
                'type' => 'file',
                'rcode' => 'a',
                'title' => 'Settings, Singer List Tab'
            ),
            'admin-ref-vp-form' => array(
                'type' => 'file',
                'rcode' => 'a',
                'title' => 'Settings, Singer Settings Form'
            ),
            'admin-ref-reporting' => array(
                'type' => 'file',
                'rcode' => 'a',
                'title' => 'Settings, Reporting Tab'
            ),
            'admin-ref-admin' => array(
                'type' => 'file',
                'rcode' => 'a',
                'title' => 'Settings, Administration Tab'
            ),
            'seg-rnote-format' => array(
                'type' => 'file',
                'rcode' => '',
                'title' => ''  // only used in shortcode
            ),
        );
        if ($need_codes) {  // Translate roles to codes to simplify filtering
            foreach ($this->table as $slug => $item) {
                $this->table[$slug]['rcode'] = $this->code($item['rcode']);
            }
        }
    }

    /**
     * @param $atts - slug of a topic
     * @return mixed|string
     *
     * The shortcode can produces 3 types of html output:
     *     1. On any page, it supplies only the content of a single topic.
     *        The topic's slug is passed in via the man parameter on the shortcode:
     *        [rn-refman man=<slug>]
     *     2. On the rn-refman page the shortcode is used without a parameter and
     *        the topic is passed in via query parameter man:
     *        /rn-refman?man=<slug>
     *        The output now includes page formatting, a header, and the topic content.
     *     3. On the rn-refman page, the query parameter can also be a request for a
     *        full reference manual.  In this case, a set of roles is passed in to
     *        specify which roles' topics should be included in the manual:
     *        /rn-refman?man=rm-snda
     *        The output now includes an entire manual.
     *
     * It has a 2nd parameter, which only works with the first - and indicates whether
     * to include a full Reference Manual Link at the top.  rm_link="_self|_blank", defaults to "".
     */
    public function html($atts)
    {
        // If the Shortcode has man parameter, return the topic content only
        $slug = isset($atts['man']) ? $atts['man'] : null;
        if ($slug) {
            // If it also has the rm_link parameter, include a link to the default Ref Manual for this user
            $rm_link = isset($atts['rm_link']) ? $atts['rm_link'] : '';
            return $this->get_rm_link($rm_link, '<br><br>') . $this->read_man_file($slug, true);
        }

        // If there is no man parameter, it must be passed in via a query parameter.
        // And this is coming from the rn-refman page, which requires additional styling.
        if(!isset($_REQUEST['man']))
            return '<div class="rm-page">ERROR: RN Reference Manual, missing topic.</div>';
        $slug = $_REQUEST['man'];

        if (substr($slug, 0, 3) != 'rm-') { // request for single topic, man = slug/filename

            $content = $this->read_man_file($slug, true);
            if (substr($content, 0, 6) == 'ERROR:') {
                $this->html = $content;
            } else {
                $this->init_table(false);
                $title = $this->table[$slug]['title'];
                $this->html .= '<h4 class="rm-header">' . $title . '</h4>' . $this->get_rm_link('_self') . '<br><br>' . $content;
            }

        } else { // Creating a full manual, this requires a parameter of rm-snda

            $roles = substr($slug, 3);  // remove rm-
            if (strlen($roles) > 4) {
                $this->html = 'ERROR: RN Reference Manual, invalid manual type.';
            } else {
                $rcode = $this->code($roles);
                if ($rcode == 0) {
                    $this->html = 'ERROR: RN Reference Manual, missing manual type.';
                } else {
                    $this->init_table(true);
                    // Don't include parts that this user does not have access to
                    $this->create_manual($rcode & $this->access);
                }
            }
        }
        // Wrap it in full page formatting
        return '<div class="rm-page">' . $this->html . '</div>';
    }

    /**
     * @param $slug
     * @return mixed|string
     *
     * The slug is both the topic ID and the base name of its HTML file.
     * It extracts and returns only the <body> portion of the file.
     *
     * The files also contain a full list of CSS files in its <header>
     * section, but that is only so that it will be properly rendered
     * while editing in BlueGriffin's WYSIWYG editor, and is thus
     * discarded when used on the server.
     */
    private function read_man_file($slug, $xlate_links, $recurse = false) {
        $man_file_name = dirname(__FILE__).'/../../assets/man/'.$slug.'.html';
        if (!file_exists($man_file_name))
            return 'ERROR: RN Reference Manual file missing for: '.$slug;

        $man_file = file_get_contents($man_file_name);
        preg_match("/<body[^>]*>(.*?)<\/body>/is", $man_file, $matches);
        if ($matches[1] == "")
            return 'ERROR: RN Reference Manual file empty: '.$slug;

        $content = preg_replace( "/\r|\n/", "", $matches[1]);

        if ($xlate_links) {
            // Refman only has targets to topics, not within a topic.  So if a topic
            // has a an internal page link, and this is topic is being requested by
            // itself, those links must be xlated to be remote.
            /** If an internal reference is used within a topic, mark it with topic#
                in front of the link, e.g. topic#link, so that it does not get
                translated here.  It is then fixed in the next line. */
            $content = str_replace('href="#', 'href="' . $this->site_url . '/rn-refman?man=', $content);
        }
        $content = str_replace('href="topic#', 'href="#', $content);

        if (!$recurse) {
            foreach ($this->segs as $seg) {
                if (strpos($content, $seg) !== false) {
                    $seg_slug = trim($seg, '[]');
                    $seg_content = $this->read_man_file($seg_slug, $xlate_links, true);
                    $content = str_replace($seg, $seg_content, $content);
                }
            }
        }
        return $content;
    }

    /**
     * @param $target = '_self', '_blank', '' => no link
     * @param string $postfix
     * @return string
     * Utility to get a default Manual Link.
     */
    private function get_rm_link($target, $postfix = '') {
        $rm_link = '';
        if (!empty($target)) {
            $rm_link = '<a href="' . $this->site_url . '/rn-refman?man=rm-' . $this->def_man .
                '" target="' . $target . '"><em>Reference Manual</em></a>' . $postfix;
        }
        return $rm_link;
    }

    /**
     * @param $rcode
     *
     * Generates a full Reference Manual based on the included roles
     * in the rcode.
     *
     * Instructions for the manual itself, and those which are not needed in
     * other parts of the website, are embedded here directly.  Any instructions
     * that need to be shared (particularly page/form manuals) are in the
     * separate HTML files.
     */
    private function create_manual($rcode) {
        $skipped = array();  // track skipped topics so their links can be translated at the end
        foreach($this->table as $slug => $topic) {
            if (($topic['rcode'] & $rcode) == 0b000) {
                if ($topic['rcode'] != 0b000)  // Some topics are never included
                    $skipped[] = $slug;
            } else {
                switch ($topic['type']) {

                    case 'title':  // Common Title and Instroduction for all Manuals
                        $this->html .= '<h3>' . $topic['title'] . '</h3>';

                        $date = new DateTime("now", new DateTimeZone("America/Los_Angeles"));
                        $this->html .= 'Created: ' . $date->format('m/d/Y. ');
                        $this->html .= 'This version includes the manuals for: ';
                        $roles = array();
                        foreach($this->crtx as $code => $role) {
                            if (($code & $rcode) != 0b0000)
                                $roles[] = $role;
                        }
                        $this->html .= implode(' / ', $roles) . '.<br><br>';

                        if (($this->access & 0b0111) != 0) {  // NT, Dir, and Admin
                            $this->html .= 'Links to available versions:<ul>';
                            $this->html .= '<li><a href="' . $this->site_url . '/rn-refman?man=rm-s">For Singers</a></li>';
                            $this->html .= '<li><a href="' . $this->site_url . '/rn-refman?man=rm-n">For Note Takers</a></li>';
                            $this->html .= '<li><a href="' . $this->site_url . '/rn-refman?man=rm-d">For Directors</a></li>';
                            switch ($this->access) {
                                case 0b1111:  // Admin and Dir
                                    $this->html .= '<li><a href="' . $this->site_url . '/rn-refman?man=rm-a">For Administrators</a></li>';
                                    $this->html .= '<li><a href="' . $this->site_url . '/rn-refman?man=rm-snda">Full Reference Manual</a></li>';
                                    break;

                                case 0b1110:  // NT
                                    $this->html .= '<li><a href="' . $this->site_url . '/rn-refman?man=rm-snd">Full Reference Manual</a></li>';
                                    break;

                                default:
                                    break;
                            }
                            $this->html .= '</ul>';
                        }

                        $this->html .= 'The manuals include:<ol>';
                        $this->html .= '<li><strong>Instructions</strong>: Brief guides for performing common tasks for each role.</li>';
                        $this->html .= '<li><strong>Topic References</strong>: In depth descriptions of items/settings on a page or form.</li></ol>';

                        $this->html .= 'The manuals are not intended to be read from cover to cover. &nbsp;Instead, you can '
                            . 'read through only those parts that are relevant to what you have questions about.&nbsp; '
                            . 'Links are provided so that you can skip from place to place as needed.&nbsp; '
                            . 'A good place to start is with the Instructions for your role(s): '
                            . '<a href="#singer-instruct">Singer</a>';
                        if ($this->singer['is_nt'])
                            $this->html .= ', <a href="#nt-instruct">Note Taker</a>';
                        if ($this->singer['is_dir'])
                            $this->html .= ', <a href="#dir-instruct">Director</a>';
                        if ($this->singer['is_admin'])
                            $this->html .= ', <a href="#admin-instruct">Administrator</a>';
                        $this->html .= '.&nbsp; Then, while working with the Notes, there are links from most pages '
                            . 'and forms that will take you directly to a <a href="#refman">Topic Reference</a> that describes each item or setting on that '
                            . 'page/form.';
                        if (($this->access & 0b0111) != 0) {  // NT, Dir, and Admin
                            $this->html .= '&nbsp; As a Rehearsal Notes Staff member, you may also want to start with an overview in the '
                                . '<a href="#staff-intro">Introduction</a>.';
                        }
                        $this->html .= '<br><br>';

                        $this->html .= 'Notes:<em><ul>';
                        $this->html .= '<li>Links to the Topic References: <span class="corner-help">?</span> can be found in the upper right corner of most pages and forms.</li>';
                        $this->html .= '<li>The manuals are designed to be printed, and in most systems they can be printed to PDF with active links.';
                        $this->html .= '<ul><li>If this does not work on your system, you can ';
                        $this->html .= '<a href="mailto:consortchorale@gmail.com?subject=Request for RN PDF Reference Manual'
                                       . '&body=Please indicate which section(s) to include: Singer, Note Taker, Director, Admin" '
                                       . 'title="Create email request for PDF">request a PDF version with active links</a>';
                        $this->html .= '.</li></ul></li>';
                        $this->html .= '<li>Links in the manuals:<ul>';
                        $this->html .= '<li>Most links in the manuals go to other topcis in the manual.</li>';
                        $this->html .= '<li>Links to pages on the website are labeled "Direct Links".</li></ul>';
                        $this->html .= '<li>Menu paths in the manuals:<ul>';
                        $this->html .= '<li>All menu paths begin at the main menu, found at top of front-end pages.</li>';
                        $this->html .= '<li>Paths are shown as: </em>Main -> Submenu -> etc.<em></li>';
                        if ($this->access != 0b1000)
                            $this->html .= '<li>Back-end Dashboard menu items are included in the paths.<ul><li>On the website, they are located in its left sidebar.</li></ul></li>';
                        $this->html .= '<li>Links in menu paths go to their respective manual topics.</li>';
                        $this->html .= '<li>[Name] refers to the main menu item that is your name when logged in.</li></ul>';
                        $this->html .= '<li>Abbreviations used in the manuals:<ul>';
                        $this->html .= '<li>RN: Rehearsal Notes</li>';
                        $this->html .= '<li>Q/N: Question/Note</li>';
                        $this->html .= '<li>TOC: Table of Contents</li>';
                        $this->html .= '<li>WP: WordPress</li>';
                        $this->html .= '</ul></li>';
                        $this->html .= '</ul></em>';
                        break;

                    case 'toc':  // Start the Table of Contents
                        $this->html_toc .= '<h4 id="toc">' . $topic['title'] . '</h4><ul>';
                        break;

                    case 'header':  // A Section Header
                        if ($this->toc_subtopic)
                            $this->html_toc .= '</ul>';
                        $this->add_title($topic, $slug);
                        if ($slug == 'instructions') {
                            $this->html_content .= '<em>Instructions are brief sets of "How to" do tasks related to each role.</em>';
                        }
                        if ($slug == 'refman') {
                            $this->html_content .= '<em>Topic Reference are detailed descriptions of each page or form. Use the
                                            TOC, "Table of Contents" to find the topic you are looking for.  While on a page
                                            or form, you can also click on the <span class="corner-help">?</span> "help" icon to see its topic.</em>';
                        }
                        $this->html_toc .= '<ul>';
                        $this->toc_subtopic = true;
                        break;

                    case 'file':  // File-based HTML manual
                        $this->add_title($topic, $slug);
                        $this->html_content .= '<a href="#toc"><em><small>back to TOC</small></em></a>';
                        $this->html_content .= '<p>' . $this->read_man_file($slug, false) . '</p>';
                        break;
                }
            }
        }
        if ($this->toc_subtopic)
            $this->html_toc .= '</ul>';
        $this->html .= $this->html_toc . '</ul>';

        $this->html .= $this->html_content;

        // Internal links within the document may be pointing to targets that were not
        // included.  For those, the links are reset to point to the website as a link
        // to the one topic.
        //   #slug => site_url/rn-refman?man=slug
        //
        if (count($skipped) > 0) {
            $local_links = array();
            $remote_links = array();
            foreach ($skipped as $slug) {
                $local_links[] = 'href="#' . $slug . '"';
                $remote_links[] = 'href="' . $this->site_url . '/rn-refman?man=' . $slug . '"';
            }
            $this->html = str_replace($local_links, $remote_links, $this->html);
        }
    }

    /**
     * @param $topic
     * @param $slug
     * Adds a Title to the manual, which includes ...
     *     An entry in the TOC, which is a link to the topic
     *     Starts the Topic in the body of the Manual, which is the link's target
     *
     * Note that inter-manual links are only made to topics.  No other targets
     * are defined within the manuals.
     */
    private function add_title($topic, $slug) {
        $this->html_toc .= '<li><a href="#' . $slug . '">' . $topic['title'] . '</a></li>';
        if ($topic['type'] == 'header') {
            $this->html_content .= '<hr class="rm-divider"><h4 id="' . $slug . '" class="rm-header">' . $topic['title'] . '</h4>';
        } else {
            $this->html_content .= '<hr class="rm-divider"><h5 id="' . $slug . '" class="rm-header">' . $topic['title'] . '</h5>';
        }
    }
}

