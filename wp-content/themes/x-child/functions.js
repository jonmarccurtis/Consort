/**
 * Created by joncu on 4/10/19.
 */
function ccx_js($) {

    $(document).ready(function () {

        /** Obfuscate the Contact us menu button */
        $("#menu-item-2982").on('click', function() {
            location.href = '/ca17it91/';
            return false;
        });

        /** Add download button to playlists, but only if member logged in **/
        // Old method - detects standard WP login
        // if ($("body").hasClass("logged-in")) {
        var dc = document.cookie;
        if (dc.includes("; wp-postpass")) {
            var $pl = $("#tmpl-wp-playlist-item");
            if ($pl.length !== 0) {
                var scr = $pl.html();
                // Concatenate the download button to the end of the playlist template (from media.php)
                $pl.html(scr + '<a href="{{ data.src }}" class="wpse-download" download=""><i class="fa fa-download" title="Download" aria-hidden="true"></i></a>');
            }
        }

        /** Email support */
        $("span.cc-send-adr").on("click", ccx_js.do_send_adr);
    });

    /**
     * Obfuscated sending using a span
     *
     * split email( adr @ srv . ext) - using ...
     * body must be a single line, with no special characters or HTML
     *
     * <span class="cc-send-adr" data-srv="srv" title="Create email" data-sub="" data-adr="adr" data-ext="ext" data-body="">Name</span>
     * Optional: add data-cc="yes" to include a cc to consort
     */
    function do_send_adr(e) {
        var $cc = (typeof $(this).data("cc") === 'undefined') ? "" :
            "&cc=con" + "sortcho" + "rale@" + "gmai" + "l.com";
        window.location="mai" + "lto:" + $(this).data("adr") + "@"
            + $(this).data("srv") + "." + $(this).data("ext") + "?subject=" + $(this).data("sub")
            + $cc + "&body=" + $(this).data("body");
        e.preventDefault();
        e.stopPropagation();
        return false;
    }
    ccx_js.do_send_adr = do_send_adr;

    /**
     * Show Video that is stored in /wp-content/cc-videos
     * On the server, this is a symlink to a separate drive for larger files, outside of SVN
     * @param title
     * @param filename = does not include extension, which must be mp4
     *
     * Example usage: assuming there's a video called edit_news.mp4 in cc-videos
     * <a href="#" onclick="ccx_js.show_video('Edit News', 'edit_news');return false;">Show Demo: Edit News</a>
     */
    function show_video(title, filename) {
        var html = "<div id='video-overlay'>";
        html += "<div id='video-content'>&nbsp;" + title + "<br>";
        html += "<div id='video-close' onclick='ccx_js.close_video()'>Close</div>";
        html += "<video controls autoplay><source src='/wp-content/cc-videos/" + filename + ".mp4' type='video/mp4'></video>";
        html += "</div></div>";
        $("body").append(html);
    }
    ccx_js.show_video = show_video;

    function close_video() {
        $("#video-overlay").remove();
    }
    ccx_js.close_video = close_video;

}
ccx_js(jQuery);
