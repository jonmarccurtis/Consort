/**
 * Created by joncu on 4/10/19.
 */
function rn_adtab($) {
    var form_dirty = false;
    var notes_modified = [];
    var editor_callback_set = false;
    var editor_tab_callback_set = false;
    var editor_note_message_callback_needed = false;
    var hb_active = true;
    var hb_timer = null;

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

        set_action_buttons_callbacks();
        set_edit_form_warning_message_controls();
        set_msg_events();

        $("a.nt-staff").on('click', function(e) {
            window.location="mai"+"lto:"+adrs.join('@');
            e.preventDefault();
            e.stopPropagation();
            return false;
        });

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

    function set_action_buttons_callbacks() {
        $(".action-btn").off('click', rn_adtab.set_action_button_events);
        $(".action-btn").on('click', rn_adtab.set_action_button_events);
    }
    function set_action_button_events(e) {
        switch ($(this).data('type')) {
            case 'move': move_action(this, e); break;
            case 'edit': edit_action(this, e); break;
            case 'dup': duplicate_action(this, e); break;
            case 'hist': show_history_action(this, e); break;
            case 'mark': highlight_action(this, e); break;
            case 'del': delete_action(this, e); break;
        }
        e.stopPropagation();
    }
    rn_adtab.set_action_button_events = set_action_button_events;

    function scroll_to(rows) {
        $.each(rows.reverse(), function() {
            if (this.is(':visible')) {
                var offset = this.offset().top - ($(window).height() / 2);
                $('html, body').animate({scrollTop: offset}, 700);
                return false;
            }
        });
    }

    // ============ HEARTBEATS ============

    function send_heartbeat() {
        var data = {
            hb_ts: hb_ts,
            hb_active: hb_active
        };
        rn_common.send_request('rn_hb', data, rn_adtab.heartbeat_ok, rn_adtab.heartbeat_fail);
    }
    rn_adtab.send_heartbeart = send_heartbeat;

    function heartbeat_ok(res) {
        $('.staff-name').each(function() {
            var id = $(this).data('id').toString();
            if (res.online_staff.includes(id)) {
                $(this).animate({ outlineColor: 'blue'}, 1000)
            } else {
                $(this).animate({ outlineColor: 'transparent'}, 1000)
            }
        });
        var online_singers = res.online_singers.join(', ');
        if ($('.active-singers').html() != online_singers) {
            $('.active-singers').html(online_singers);
            set_msg_events();
        }

        clear_modified_marks();
        hb_ts = res.new_ts;
        if (res.changes.length > 0 || res.done_chg.length > 0) {
            $.each(res.changes, function() {
                if ($('#rntr-'+this.note_id).length) {
                    if (this.del) {
                        $("#rntr-" + this.note_id).remove();
                        if ($("#rn-edit-overlay").css('display') == 'flex') {
                            // Edit form is open
                            if ($('#note_id').val() == this.note_id) {
                                set_footer('pending', 'Note has been deleted by Admin', ['close']);
                            }
                        }
                    } else {
                        $("#rntr-" + this.note_id).html(this.mod.html);
                        if ($("#rn-edit-overlay").css('display') == 'flex') {
                            // Edit form is open
                            if ($('#note_id').val() == this.note_id) {
                                show_edit_warning(this.mod);
                            }
                        }
                    }
                } else {
                    if (!this.del)
                        $("#rn_table tr:last").after(this.new.html);
                }
                set_modified_mark($("#rntr-"+this.note_id));
            });
            $.each(res.done_chg, function() {
                $done = $('#done-'+this.note_id);
                $done.html(this.count);
                set_modified_done($done);
            });
            setTimeout(delay_update, 7000);  // allow moves to appear in pre/post locations
            set_action_buttons_callbacks();
            set_msg_events();
        }
        hb_timer = setTimeout(send_heartbeat, 15000);
    }
    rn_adtab.heartbeat_ok = heartbeat_ok;

    function delay_update() {
        rn_table.update_table();
    }
    rn_adtab.delay_update = delay_update;

    function heartbeat_fail() {
        hb_timer = setTimeout(send_heartbeat, 15000);
    }
    rn_adtab.heartbeat_fail = heartbeat_fail;

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
    function addNoteMessage(target) {
        setNote('<em style="color: #888888;">Write '+target+' here ...</em>');
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
    rn_adtab.setFormDirty = setFormDirty;

    /**
     * Generic form for all note editing.  Has several version - noted below
     * @param target = question, note, dup, edit
     * @param settings
     */
    function show_edit_form(target, settings = null) {
        $('#edit-title-author').hide();
        if (settings == null) {
            // New Note or Question
            set_move_dropdown_for('new', 0);
            if (target == 'question') {
                $("#edit-title").html("Ask Question for Directors");
                $("#location").val('dir-inbox');
                $("#author_id").val($("author_id option:first").val());
                $('#edit-title-author').show();
            } else {
                $("#edit-title").html("Add Rehearsal Note");
                $("#location").val('review');
            }
            $(".edit-location").show();
            addNoteMessage(target);
            $('#song-attrs').find("input,select").prop('disabled', false);
            $('.edit-warning').hide();

            // song - don't change from last setting
            // director - also don't change
            $('#measure').val('');
            $('#discussion').val('');
            $('#note_id').val(0);
            set_song();
            set_footer('', '', ['cancel','save']);

        } else { // Duplicate or Edit Question or Note

            // Distinguish Q from N by its location
            if (['nt-inbox','dir-inbox'].includes(settings.location))
                target = 'Question';
            else
                target = 'Rehearsal Note';

            var pub_read = false;

            if (settings.note_id == 0) {
                // Duplicating an existing note - so this one is new
                $("#edit-title").html("Duplicate " + target);
                $('#song-attrs').find("input,select").prop('disabled', false);
                $('.edit-warning').hide();

                set_move_dropdown_for('new', 0);
                if (target == 'Question')
                    $("#location").val('dir-inbox');
                else
                    $("#location").val('review');
                $(".edit-location").show();
                set_footer('', '', ['cancel','save']);

            } else { // Editing an existing Note
                // Published restrictions depend on whether any done checked
                pub_read = (settings.location == 'published' && settings.done > 0);

                var pub = pub_read ? 'Published ' : '';
                $("#edit-title").html("Edit " + pub + target);

                // Admins can still edit the identifying information
                $('#song-attrs').find("input,select").prop('disabled', pub_read && (rn_role != 'ADMIN'));
                if (pub_read)
                    $('.edit-warning').show();
                else
                    $('.edit-warning').hide();

                set_move_dropdown_for(settings.location, settings.done);
                if (settings.location == 'dir-inbox' && rn_role == 'DIR') {
                    $("#location").val('review');
                    $(".edit-location").hide();
                } else {
                    $("#location").val(settings.location);
                    $(".edit-location").show();
                }
                if (settings.modified)
                    set_footer('pending', 'Note has been modified by '+settings.author+'.<br>This is the new version.', ['cancel','save']);
                else
                    set_footer('', '', ['cancel','save']);
            }

            $('#sel-songs').val(settings.song_id);
            $('#dir-id').val(settings.dir_id);
            $('#measure').val(settings.measure);
            $('#start-ms').html(songs[settings.song_id][rn.sm] != 0 ? songs[settings.song_id][rn.sm] : '');
            $('#end-ms').html(songs[settings.song_id][rn.em] != 0 ? songs[settings.song_id][rn.em] : '');

            $('#parts').html(rn_common.get_vps_control(
                songs[settings.song_id][rn.vp], settings.note_vps, '', pub_read && (rn_role != 'ADMIN')));
            rn_common.set_vps_control_events();

            setNote(settings.note);
            $('#discussion').val(settings.discussion);
            $('#note_id').val(settings.note_id);
            $('#note_ts').val(settings.note_ts);
        }
        setEditorCallback();
        form_dirty = false;
        $("#rn-edit-overlay").css('display', 'flex');
    }
    rn_adtab.show_edit_form = show_edit_form;

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

    // Edit Form Warning Message
    function set_edit_form_warning_message_controls() {
        $("#rn-overlay-warning").on('click', function() {
            $(this).removeClass('on-bottom');
        });
        $("#rn-overlay-main").on('click', function() {
            $("#rn-overlay-warning").addClass('on-bottom');
        });
    }

    /**
     * Controls the Move dropdown.  Expects the option ids to
     * be "loc-slug".  All options should be present on page load
     */
    var locs = {
        'nt-inbox': 'new,nt-inbox,dir-inbox,review,trash,pub-unread',
        'dir-inbox': 'new,nt-inbox,dir-inbox,review,trash,pub-unread',
        'review': 'new,nt-inbox,dir-inbox,review,trash,pub-unread',
        'published': 'review,pub-unread,pub-read',
        'trash': 'nt-inbox,dir-inbox,review,trash,pub-unread',
    };
    function set_move_dropdown_for(location, done) {
        set_move_options_for(location, '#loc-', false, done);
        var def_loc = location;
        if (def_loc == 'new')
            def_loc = (rn_role == 'DIR') ? 'dir-inbox' : 'nt-inbox';
        $('#location').val(def_loc);
    }
    function set_move_options_for(loc, prefix, mark_self, done) {
        var loc_mark = loc;
        if (loc == 'published') {
            loc = (done == 0) ? 'pub-unread' : 'pub-read';
        }
        Object.keys(locs).forEach(function(option) {
            // Safari does not support hiding of options
            if (locs[option].includes(loc)) {
                $(prefix + option).show();
                $(prefix + option).prop('disabled', false);
            } else {
                $(prefix + option).hide();
                $(prefix + option).prop('disabled', true);
            }
            if (mark_self) {
                if (option == loc_mark)
                    $(prefix + option + '-icon').show();
                else
                    $(prefix + option + '-icon').hide();
            }
        });
    }

    function save_rnote() {
        if (!form_dirty) {
            set_footer('error', 'Nothing has changed, nothing to save');
            return;
        }
        // Admins don't have attrs_disabled for pub_read notes
        var attrs_disabled = !!$('#song-attrs input').prop('disabled');
        var pub_read = !!$('.edit-warning').is(':visible');

        var clear_done = false;
        if (pub_read) {
            clear_done = confirm(
                'WARNING: You are changing a note that has already been copied by singers.' +
                '\n\nDo you want to clear everyone\'s "Done" status?' +
                '\n\n[Cancel] SAVE without clearing "Done".' +
                '\n[OK]       SAVE and CLEAR everyone\'s "Done" status so they know to update their music (recommended). ');
        }

        if (attrs_disabled)
            $('#song-attrs').find("input,select").prop('disabled', false);
        var data = {
            rn_note: getNote(),
            note_vps: rn_common.get_vps_selection(),
            pub_read: pub_read,
            clear_done: clear_done
        };
        var pars = $("#edit-form").serializeArray();
        for (var i = 0; i < pars.length; i++) {
            if (pars[i]['name'] != 'note-editor')
                data[pars[i]["name"]] = pars[i]["value"];
        }
        if (attrs_disabled)
            $('#song-attrs').find("input,select").prop('disabled', true);

        if ($('#loc-'+data.note_id).data('loc') != 'trash' && data.location == 'trash') {
            add_reason_for_trash('rn_sv', data, rn_adtab.save_ok, rn_adtab.save_fail);
            return;

        } else if (data.location == 'published') {
            // data.note_id should always be set if target is published
            if (!check_publishing_author(data.note_id))
                return;
        }
        rn_common.send_request('rn_sv', data, rn_adtab.save_ok, rn_adtab.save_fail);
    }
    rn_adtab.save_rnote = save_rnote

    function save_ok(res) {
        switch(res.state) {
            case 'new':
                $("#rn_table tr:last").after(res.html);
                var msg = 'Add Rehearsal Note succeeded.';
                set_footer('success', msg, ['close', 'add']);
                notes_modified.push($("#rntr-"+res.note.note_id));
                form_dirty = false;
                break;

            case 'saved':
                $("#rntr-"+res.note.note_id).html(res.html);
                notes_modified.push($("#rntr-"+res.note.note_id));
                form_dirty = false;
                close_edit_form();
                break;

            case 'modified':
                $('#rntr-'+res.note.note_id).html(res.html);
                show_edit_warning(res);
                break;
        }
    }
    rn_adtab.save_ok = save_ok;

    function show_edit_warning(res) {
        $('#note_ts').val(res.note.note_ts); // enables 2nd save to succeed
        $("#rntr-warning").html(res.warning);
        $("#rn-overlay-warning").removeClass('on-bottom');
        $("#warning-author").html(res.note.author);
        $("#rn-overlay-warning").show();
    }

    function save_fail(msg) {
        set_footer('error', msg);
    }
    rn_adtab.save_fail = save_fail;

    function close_edit_form() {
        if (form_dirty) {
            if (!confirm("Changes you made may not be saved."))
                return;
        }
        form_dirty = false;

        if (notes_modified.length > 0) {
            rn_table.update_table();
            rn_table.set_selected_rows(notes_modified);
            scroll_to(notes_modified);
            set_action_buttons_callbacks();
            set_msg_events();
            notes_modified = [];
        }
        $("#rn-edit-overlay").css('display', 'none');
        $("#rn-overlay-warning").hide();
    }
    rn_adtab.close_edit_form = close_edit_form;

    function set_song() {
        var song_id = $("#sel-songs").find(":selected").attr("id").replace("song-", "");
        var song = songs[song_id];
        $("#dir-id").val(song[rn.dir]);

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

    // Call if target location is published.  If it is currently
    // being moved, then make sure its not the same editor.
    function check_publishing_author(note_id) {
        // Current location and author
        if ($('#loc-'+note_id).data('loc') == 'review') {
            if ($('#editor-'+note_id).data('editor_id') == author_id) {
                return confirm(
                    'CAUTION: Rehearsal Notes should be viewed by at least 2 people before publishing.' +
                    'You are the one who moved this note to Review. ' +
                    'Are you sure you also want to publish it?');
            }
        }
        return true;
    }

    function add_reason_for_trash(n_par, data, success_fcn, fail_fcn) {
        var $form = $('form#trash-form');
        $form.data('n_par', n_par);
        $form.data('data', data);
        $form.data('success_fcn', success_fcn);
        $form.data('fail_fcn', fail_fcn);

        $form.find('input[type=checkbox]').prop('checked', false);
        $form.find('input[type=text]').val('');

        $('#rn-trash-overlay').css('display', 'flex');
    }

    function save_trash() {
        var $form = $('form#trash-form');
        var n_par = $form.data('n_par');
        var data = $form.data('data');
        var success_fcn = $form.data('success_fcn');
        var fail_fcn = $form.data('fail_fcn');

        var disc = data['discussion'];
        disc += ' <b>Trash</b>';
        var pars = $form.serializeArray();
        var reasons = 0;
        for (var i = 0; i < pars.length; i++) {
            if (pars[i]["value"].length > 0) {
                disc += ', ' + pars[i]["value"];
                reasons++;
            }
        }
        if (reasons == 0) {
            alert('Please indicate why the question/note is being sent to Trash.');
            return;
        }
        data['discussion'] = disc;

        rn_common.send_request(n_par, data, success_fcn, fail_fcn);
        $('#rn-trash-overlay').css('display', 'none');
    }
    rn_adtab.save_trash = save_trash;

    function cancel_trash() {
        $('#rn-trash-overlay').css('display', 'none');
    }
    rn_adtab.cancel_trash = cancel_trash;

    function set_msg_events() {
        $(".send-adr").off("click", rn_adtab.do_msg);
        $(".send-adr").on("click", rn_adtab.do_msg);
    }
    function do_msg(e) {
        window.location="mai"+"lto:"+$(this).data("adr")+"@"
            +$(this).data("srv")+"?subject=[RNote] "+$(this).data("note");
        e.preventDefault();
        e.stopPropagation();
        return false;
    }
    rn_adtab.do_msg = do_msg;

    // ========= MODIFIED MARKERS ==========

    function set_modified_mark($note) {
        $note.addClass('rn-mod-mark')
        $note.animate({ outlineColor: 'blue' }, 1000);
    }
    function clear_modified_marks() {
        $('.rn-mod-mark').animate({ outlineColor: 'transparent' }, 1000);
        $('.rn-mod-mark').removeClass('rn-mod-mark');
        $('.rn-mod-done').removeClass('rn-mod-done');
    }
    function set_modified_done() {
        $done.addClass('rn-mod-done');
    }

    // ========= ACTIONS ==========

    // MOVE ACTION ...
    function move_action(action, e) {
        var note_id = $(action).data('id');
        if (!$('#rntr-'+note_id).hasClass('rn-mod-mark')) {
            var loc = $('#loc-' + note_id).data('loc');
            var done = $('#done-' + note_id).html();
            var parent = $(action);
            var $overlay = $('#rn-move-overlay');
            var $form = $('#rn-move-overlay-form');

            $overlay.on('click', move_disable_screen);
            $form.position({
                of: parent,
                my: 'right top',
                at: 'bottom left',
                collision: 'flip none'
            });

            // set up the available move options
            set_move_options_for(loc, '#move-to-', true, done);
            $('.move-to-btn').on('click', {note_id: note_id, loc: loc}, do_move_action);
            $overlay.show();
        }
    }
    function move_disable_screen() {
        $(this).hide();
        var $form = $('#rn-move-overlay-form');
        $form.css('top', '0');
        $form.css('left', '0');
        $(this).off('click', move_disable_screen);
        $('.move-to-btn').off('click', do_move_action);
    }
    function do_move_action(e) {
        var id = e.data.note_id;
        var data = {
            note_id: id,
            from: $('#loc-'+id).data('loc'),
            to: $(this).attr('id').replace('-btn', '')
        };

        if (data.from != 'trash' && data.to == 'trash') {
            data.discussion = $('#rntr-' + id).find('.disc-col').html();
            add_reason_for_trash('rn_mv', data, rn_adtab.move_ok, rn_adtab.action_fail);
            return;

        } else if (data.to == 'published') {
            if (!check_publishing_author(id))
                return;
        }
        rn_common.send_request('rn_mv', data, rn_adtab.move_ok, rn_adtab.action_fail);
    }

    function move_ok(res) {
        var $note = $("#rntr-"+res.note.note_id);
        $note.html(res.html);
        rn_table.update_table();
        rn_table.set_selected_rows([$note]);
        scroll_to([$note]);
        set_action_buttons_callbacks();
        set_msg_events();

        if (res.state == 'modified') {
            set_modified_mark($note);
            alert('WARNING: This note has just been modified by ' + res.note.author
                + ' and moved to "' + res.note.loc_name + '".');
        }
    }
    rn_adtab.move_ok = move_ok;

    function action_fail(msg) {
        alert(msg);
    }
    rn_adtab.action_fail = action_fail;


    // EDIT ACTION ...
    function edit_action(action, e) {
        if (!$(action).hasClass('rn-mod-mark')) {
            var note_id = $(action).data('id');
            var data = {note_id: note_id};
            rn_common.send_request('rn_gt', data, rn_adtab.edit_ok, rn_adtab.action_fail);
        }
    }
    function edit_ok(res) {
        var ts_div = $('#ts-'+res.note.note_id);
        if ($('#ts-'+res.note.note_id).data('ts') != res.note.note_ts) {
            var $note = $('#rntr-'+res.note.note_id);
            $note.html(res.html);
            set_modified_mark($note);
            res.note.modified = true;
        } else
            res.note.modified = false;
        show_edit_form('edit', res.note);
    }
    rn_adtab.edit_ok = edit_ok;


    // DUPLICATE ACTION ...
    function duplicate_action(action, e) {
        var note_id = $(action).data('id');
        var data = {note_id: note_id};
        rn_common.send_request('rn_gt', data, rn_adtab.dup_ok, rn_adtab.action_fail);
    }
    function dup_ok(res) {
        res.note.note_id = 0;
        show_edit_form('duplcate', res.note);
    }
    rn_adtab.dup_ok = dup_ok;


    // SHOW HISTORY ACTION ...
    function show_history_action(action, e) {
        data = {
            note_id: $(action).data('id')
        };
        rn_common.send_request('rn_hi', data, rn_adtab.history_ok, rn_adtab.action_fail);
    }

    function history_ok(res) {
        $('#rn-history').html(res);
        set_msg_events();
        $("#rn-history-overlay").css('display', 'flex');
    }
    rn_adtab.history_ok = history_ok;

    function close_history() {
        $("#rn-history-overlay").css('display', 'none');
    }
    rn_adtab.close_history = close_history;

    // HIGHLIGHT ACTION ...
    function highlight_action(action, e) {
        var $row = $('#rntr-'+$(action).data('id'));
        rn_table.set_selected_rows([$row], e.altKey,
            !rn_table.is_selected_row($row));
    }

    // DELETE ACTION ... backdoor for Admin only
    function delete_action(action, e) {
        if (confirm('Are you sure you want to completely delete this note?'+
            ' It will no longer show up in searches or duplicate checking.')) {
            var note_id = $(action).data('id');
            var data = {note_id: note_id};
            rn_common.send_request('rn_del', data, rn_adtab.del_ok, rn_adtab.action_fail);
        }
    }
    function del_ok(res) {
        $('#rntr-'+res.note.note_id).remove();
    }
    rn_adtab.del_ok = del_ok;

}
rn_adtab(jQuery);


