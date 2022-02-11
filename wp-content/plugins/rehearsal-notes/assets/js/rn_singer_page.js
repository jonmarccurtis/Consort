/**
 * Created by joncu on 4/10/19.
 */
function rn_stab($) {
    var form_dirty = false;
    var editor_callback_set = false;
    var editor_tab_callback_set = false;
    var editor_note_message_callback_needed = false;
    var hb_active = true;
    var hb_timer = null;
    var notes_del = null;

    $(document).ready(function () {
        $("#edit-form :input").change(function() {
            // This catches all changes except the MCE editor
            setFormDirty();
        });
        $(window).on('beforeunload', function(e) {
            if (form_dirty) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        $('#sel-songs').on('change', function() {
            set_song();
        });

        set_select_note_callbacks();
        set_done_events();
        $('#my-note-filter').on('click', my_note_filter);

        $(window).on("blur focus", function(e) {
            hb_active = (e.type == "focus");
            var prevType = $(this).data("prevType");

            if (prevType != e.type) {   //  reduce double fire issues
                switch (e.type) {
                    case "blur":
                        hb_active = false;
                        break;
                    case "focus":
                        hb_active = true;
                        clearTimeout(hb_timer);
                        send_heartbeat();  // refresh right away
                        break;
                }
            }

            $(this).data("prevType", e.type);
        });
        // Start our heart beating
        send_heartbeat();
    });

    function set_select_note_callbacks() {
        $('.master_row').off('click', rn_stab.set_select_note_events);
        $('.master_row').on('click', rn_stab.set_select_note_events);
    }
    function set_select_note_events(e) {
        var $row = $(this);
        rn_table.set_selected_rows([$row], e.altKey,
            !rn_table.is_selected_row($row));
    }
    rn_stab.set_select_note_events = set_select_note_events;

    // ============ DONE CHECKS ============

    function set_done_events() {
        $('.done_check').off('click', rn_stab.done_event);
        $('.done_check').on('click', rn_stab.done_event);
    }
    function done_event(e) {
        data = {
            'note_id': $(this).data('id'),
            'done': $(this).prop('checked') ? '1' : '0',
            'rnda': 'set'
        };
        rn_common.send_request('rn_sdn', data, rn_stab.done_ok, rn_stab.done_fail);
    }
    rn_stab.done_event = done_event;

    function done_ok (res) {
        rn_table.update_table();
    }
    rn_stab.done_ok = done_ok;

    function done_fail(msg) {
        alert(msg+' - Reload the page and try again.');
    }
    rn_stab.done_fail = done_fail;

    // ============ HEARTBEATS ============

    function send_heartbeat() {
        var data = {
            hb_ts: hb_ts,
            hb_active: hb_active
        };
        // If heartbeat fails - show alert once and quit
        rn_common.send_request('rn_shb', data, rn_stab.heartbeat_ok, rn_stab.heartbeat_fail);
    }
    rn_stab.send_heartbeart = send_heartbeat;

    function heartbeat_ok(res) {
        clear_modified_marks();
        hb_ts = res.new_ts;
        if (res.changes.length > 0 ) {
            $.each(res.changes, function() {
                if ($('#rntr-'+this.note_id).length) {
                    if (this.mod.note.location == 'published') {
                        $("#rntr-" + this.note_id).html(this.mod.html);
                        set_modified_mark($("#rntr-"+this.note_id), 'UPDATED');
                    } else {
                        set_modified_mark($("#rntr-"+this.note_id), 'DELETED');
                        delay_delete(this.note_id);
                    }
                } else {
                    if (this.new.note.location == 'published') {
                        $("#rn_table tr:last").after(this.new.html);
                        set_modified_mark($("#rntr-" + this.note_id), 'NEW');
                    }
                }
            });
            rn_table.update_table();
            set_select_note_callbacks();
            set_done_events();
        }
        hb_timer = setTimeout(send_heartbeat, 15000);
    }
    rn_stab.heartbeat_ok = heartbeat_ok;

    function delay_delete(note_id) {
        if (notes_del == null) {
            notes_del = [];
            setTimeout(do_delayed_delete, 10000);
        }
        notes_del.push(note_id);
    }
    function do_delayed_delete() {
        if (notes_del != null) {
            $.each(notes_del, function(idx, note_id) {
                $("#rntr-" + note_id).remove();
            });
            rn_table.update_table();
            set_select_note_callbacks();
            set_done_events();
            notes_del = null;
        }
    }
    rn_stab.do_delayed_delete = do_delayed_delete;

    function heartbeat_fail() {
        hb_timer = setTimeout(send_heartbeat, 15000);
    }
    rn_stab.heartbeat_fail = heartbeat_fail;

    // ============ EDITOR DEFAULT MESSAGE ============

    function setEditorCallback() {
        // It is impossible to know when the MCE editor gets initialized.
        // This gets called when the form opens.  If the MCE editor is not
        // the active tab, then it will not be initialized.  So, it places
        // a callback on the editor's Tab button.  When that is clicked,
        // we give it a half second to initialize and then add the callback.
        if (!editor_callback_set) {
            var editor = tinyMCE.get('note-editor');
            if (editor) {
                editor.on('change', function () {
                    setFormDirty();
                });
                if (editor_note_message_callback_needed) {
                    editor.on('click', removeNoteMessage);
                    editor_note_message_callback_needed = false;
                }
                $('#note-editor-tmce').off('click', setDelayedEditorCallback);
                editor_callback_set = true;
            } else {
                if (!editor_tab_callback_set) {
                    $("#note-editor-tmce").on('click', setDelayedEditorCallback);
                    editor_tab_callback_set = true;
                }
            }
        }
    }

    function setDelayedEditorCallback() {
        setTimeout(setEditorCallback, 500);
    }

    // Used for temporary help message in the editor
    function addNoteMessage() {
        setNote('<em style="color: #888888;">Write question here ...</em>');
        $("#wp-note-editor-editor-container").on('click', removeNoteMessage);
        var editor = tinyMCE.get('note-editor');
        if (editor) {
            editor.on('click', removeNoteMessage);
        } else {  // wait for it
            editor_note_message_callback_needed = true;
        }
    }
    function removeNoteMessage() {
        // Unfortunately switching editor tabs can cause this call to set the dirty
        // flag - so don't let the call change the flag.
        var save_dirty = form_dirty;
        var note = getNote().trim();
        // Make sure it is the default message that is being erased
        if (note.includes('<em style="color: #888888;">Write '))
            setNote('');
        form_dirty = save_dirty;

        $("#wp-note-editor-editor-container").off('click', removeNoteMessage);
        var editor = tinyMCE.get('note-editor');
        if (editor) {
            editor.off('click', removeNoteMessage);
        }
    }

    function setFormDirty() {
        if (!form_dirty) {
            set_footer('','');
            form_dirty = true;
        }
    }
    rn_stab.setFormDirty = setFormDirty;

    function clearFormDirty() {
        form_dirty = false;
    }
    rn_stab.clearFormDirty = clearFormDirty;

    function isFormDirty() {
        return form_dirty;
    }
    rn_stab.isFormDirty = isFormDirty;

    /**
     * Generic form for all note editing.  Has several version - noted below
     * @param target = question, note, dup, edit
     * @param settings
     */
    function show_edit_form() {
        $("#edit-title").html("<div id='edit-title-bar'><div>Ask Question for Directors</div>"+
            "<div id='edit-title-help'>Click [?] below for help ...</div></div>");
        addNoteMessage();
        // song - don't change from last setting
        $('#measure').val('');
        set_song();
        set_footer('', '', ['cancel', 'save']);

        setEditorCallback();
        form_dirty = false;
        $("#rn-edit-overlay").css('display', 'flex');
    }
    rn_stab.show_edit_form = show_edit_form;

   function setNote(note) {
        var editor = tinymce.get("note-editor");
        if (editor && !editor.isHidden()) {
            editor.setContent(note);
        } else {
            $("#note-editor").val(note);
        }
    }
    function getNote() {
        var editor = tinymce.get("note-editor");
        if (editor && !editor.isHidden()) {
            return editor.getContent();
        } else {
            return $("#note-editor").val();
        }
    }

    // Resets the footer message and buttons
    function set_footer(msg_type, msg, buttons = null) {
        $('#message').removeClass("rn-success rn-error rn-pending");
        if (msg_type != '')
            $('#message').addClass("rn-" + msg_type);
        $('#message').html(msg);
        if (buttons) {
            $('#edit-form div.overlay-footer button').each(function () {
                var id = $(this).attr('id');
                var name = id.replace('btn-', '');
                if (buttons.includes(name))
                    $(this).show();
                else
                    $(this).hide();
            });
        }
    }

    function save_rnote() {
        if (!form_dirty) {
            set_footer('error', 'Nothing has changed, nothing to send');
            return;
        }
        var data = {
            rn_note: getNote(),
            note_vps: rn_common.get_vps_selection()
        };
        var pars = $("#edit-form").serializeArray();
        for (var i = 0; i < pars.length; i++) {
            if (pars[i]['name'] != 'note-editor')
                data[pars[i]["name"]] = pars[i]["value"];
        }
        rn_common.send_request('rn_ssv', data, rn_stab.save_ok, rn_stab.save_fail);
    }
    rn_stab.save_rnote = save_rnote

    function save_ok(res) {
        var msg = 'Your question has been sent.';
        set_footer('success', msg, ['close', 'add']);
        form_dirty = false;
    }
    rn_stab.save_ok = save_ok;

    function save_fail(msg) {
        set_footer('error', msg);
    }
    rn_stab.save_fail = save_fail;

    function close_edit_form() {
        if (form_dirty) {
            if (!confirm("Changes you made may not be saved."))
                return;
        }
        form_dirty = false;
        $("#rn-edit-overlay").css('display', 'none');
    }
    rn_stab.close_edit_form = close_edit_form;

    function set_song() {
        var song_id = $("#sel-songs").find(":selected").attr("id").replace("song-", "");
        var song = songs[song_id];
        $("#dir-name").html(dirs[song[rn.dir]]);
        $("#dir_id").val(song[rn.dir]);

        $('#start-ms').html(song[rn.sm] != 0 ? song[rn.sm] : '');
        $('#end-ms').html(song[rn.em] != 0 ? song[rn.em] : '');

        // Bring over as many existing VPs as can
        var new_vps = '';
        if ($('#parts').html() == '') {
            // Hasn't been initialized yet - so default to selecting all
            new_vps = song[rn.vp];
        } else {
            new_vps = rn_common.get_vps_selection();
        }
        $('#parts').html(rn_common.get_vps_control(song[rn.vp], new_vps));
        rn_common.set_vps_control_events();
    }


    // ========= MODIFIED MARKERS ==========

    function set_modified_mark($note, msg) {
        $note.addClass('rn-mod-mark')
        $note.find('.done_check').prop('disabled', true);
        $note.animate({ outlineColor: 'blue' }, 1000);
        var $marker = $note.find('div.note-marker');
        $marker.html(msg);
        $marker.animate({opacity: 1.0}, 1000);
    }
    function clear_modified_marks() {
        var $notes = $('.rn-mod-mark');
        $notes.animate({ outlineColor: 'transparent' }, 1000);
        $notes.find('div.note-marker').animate({ opacity: 0 }, 1000);
        $notes.removeClass('rn-mod-mark');
        $notes.find('.done_check').prop('disabled', false);
    }

    // ========= ACTIONS ==========

    function action_fail(msg) {
        alert(msg);
    }
    rn_stab.action_fail = action_fail;

    // Track Questions
    function track_questions() {
        rn_common.send_request('rn_str', {}, rn_stab.track_ok, rn_stab.action_fail);
    }
    rn_stab.track_questions = track_questions;

    function track_ok(res) {
        $('#history-edit-title').html('Questions asked by '+res.name);
        $('.track-table').html(res.html);
        $(".send-adr").on("click", rn_stab.do_msg);
        $("#rn-history-overlay").css('display', 'flex');
    }
    rn_stab.track_ok = track_ok;

    function do_msg() {
        if ($(this).data('adr') == 'NTS') {
            window.location = "mai" + "lto:" + adrs.join('@')
                + "?subject=[RNote] " + $(this).data("note");
        } else {
            window.location = "mai" + "lto:" + $(this).data("adr") + "@"
                + $(this).data("srv") + "?subject=[RNote] " + $(this).data("note");
        }
    }
    rn_stab.do_msg = do_msg;

    function close_track() {
        $(".send-adr").off("click", rn_stab.do_msg);
        $("#rn-history-overlay").css('display', 'none');
    }
    rn_stab.close_track = close_track;

    function printable_page() {
        var notes = "";
        $.each($("#rn_table tr"), function() {
            var id = $(this).attr("id");
            if (typeof id === "undefined" || !id.startsWith("rntr-"))
                return;
            if ($(this).hasClass("filtered"))
                return;
            notes += id.substring(5)+",";
        });
        location.href = '/rn-print?nt='+notes;
    }
    rn_stab.printable_page = printable_page;

    // SET MY NOTES FILTER
    function my_note_filter(e) {
        rn_sef.edit_singer();
        e.preventDefault();
        e.stopPropagation();
        return false;
    }
    rn_stab.my_note_filter = my_note_filter;
}
rn_stab(jQuery);


