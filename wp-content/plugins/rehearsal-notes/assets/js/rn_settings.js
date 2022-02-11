/**
 * Created by joncu on 4/10/19.
 */
var rn_dirty_enabled = true;
function rn_js($) {
    var form_dirty = false;
    var rnotes = false;
    var vps_selector = false;

    $(document).ready(function () {
        $('.action-warning').on('click', function (e) {
            if (!confirm('Are you sure you want to delete all Rehearsal Notes data?')) {
                e.preventDefault();
            }
        });

        // This will tell us if the RNotes are on this tab
        rnotes = $('.sl-table').length;

        $('#rn-settings-form').submit(function () {
            if (rnotes) {
                rn_js.readSongList(true);
                $('input[name="' + rn_song_list_id + '"').val(JSON.stringify(rn_songs));
            }
            form_dirty = false;
        });

        if (rnotes)
            drawSongList();

        $(":input").change(function() {
            form_dirty = true;
        });
        $(window).on('beforeunload', function(e) {
            if (rn_dirty_enabled && form_dirty) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        vps_selector = $('#rn-vps-overlay').length;
        if (vps_selector) {
            set_vps_settings_events();
        }
    });

    function set_vps_settings_events() {
        $('.vps-setting').off('click', show_vps_selector);
        $('.vps-setting').on('click', show_vps_selector);
    }
    rn_js.set_vps_settings_events = set_vps_settings_events;

    function drawSongList() {
        var html = '';
        rn_songs.forEach(function(song, index) {
            html += drawSongRow(song, index);
        });
        $('#song-list').html(html);
        $('#add-song').html(drawSongRow(rn_defs, 'a'));
    }

    function drawSongRow(song, id) {
        var row = '<tr id="row' + id + '"' + ((id != 'a') ? ' class="song-row"' : '')
            + ' data-song-id="' + song[rn.id] + '">';
        row += drawButton(id, song) + drawSong(id, song) + drawDir(id, song)
                + drawMeasures(id, song) + drawVoiceParts(id, song);
        row += '</tr>';
        return row;
    }

    function drawButton(id, song) {
        if (rn_songs_w_notes.includes(song[rn.id])) {
            var msg = 'Songs which have assigned Rehearsal Notes cannot be deleted.  (See Instruction Manual)';
            return '<td style="padding-left:4px" title="' + msg + '" onclick="alert(\'' + msg + '\');"><span class="genericon-unapprove"></span></td>';
        }
        return '<td><button type="button" onclick="rn_js.doAction(\'' + id + '\')">'
            + ((id == 'a') ? '+' : 'x') + '</button></td>';
    }

    function drawSong(id, song) {
        return '<td><input type="text" id="song' + id + '" value="' + song[rn.name] + '" /></td>';
    }

    function drawDir(id, song) {
        var html = '<td><select id="dir' + id + '">';
        $.each(rn_dirs, function (dir_id) {
            html += '<option value="' + dir_id + '"';
            if (dir_id == song[rn.dir])
                html += ' selected="selected"';
            html += '>' + rn_dirs[dir_id] + '</option>';

        });
        return html + '</select></td>';
    }

    function drawMeasures(id, song) {
        return '<td><span class="nowrap"></span><input type="number" id="sm' + id + '" min="1" oninput="validity.valid||(value=\'\');" value="' + song[rn.sm] + '" />'
            + '<input type="number" id="em' + id + '" min="1" oninput="validity.valid||(value=\'\');" value="' + song[rn.em] + '" /></span></td>';
    }

    function drawVoiceParts(id, song) {
        return '<td><input type="text" id="vps' + id + '" value="' + song[rn.vp] + '" /></td>';
    }

    function doAction(id) {
        readSongList(false);
        if (id == 'a') {
            rn_songs.push(readRow(id));
        } else {
            rn_songs.splice(id, 1);
        }
        drawSongList();
        form_dirty = true;
    }
    rn_js.doAction = doAction;

    function readSongList(inc_add_row) {
        // Refresh song array with any changes in the fields
        rn_songs = [];
        $(".song-row").each(function () {
            var id = $(this).attr('id').substring(3);
            rn_songs.push(readRow(id));
        });
        if (inc_add_row) {
            var add_row = readRow('a');
            if (add_row[rn.name] != '')
                rn_songs.push(add_row);
        }
    }
    rn_js.readSongList = readSongList;

    function readRow(id) {
        var song_id = $('#row' + id).data('song-id');
        var song = $('#song' + id).val();
        var dir = $('#dir' + id).val();
        var sm = $('#sm' + id).val();
        var em = $('#em' + id).val();
        var vps = $('#vps' + id).val();

        return [song_id, song, dir, sm, em, vps];
    }

    // ************** Singer's List VPS Form ***************

    function show_vps_selector(e) {
        var parent = $(this);
        var $overlay = $('#rn-vps-overlay');
        var $form = $('#rn-vps-overlay-form');

        var $info = $(this).find('span');
        var id = $info.attr('id').split('-');
        var singer_id = id[1];
        var song_id = id[2];
        var vps = $info.html();

        $('#singer-id').val(singer_id);
        $('#song-id').val(song_id);
        $('#vps-info').html(rn_common.get_vps_control(rn_songs[song_id], vps));
        rn_common.set_vps_control_events();

        $form.position({
            of: parent,
            my: 'right top',
            at: 'bottom left',
            collision: 'flip none'
        });
        $overlay.show();

        e.preventDefault();
        e.stopPropagation();
        e.returnValue = '';
        return '';
    }

    function hide_vps_selector() {
        var $selector = $('#rn-vps-overlay');
        $selector.hide();
        var $form = $('#rn-vps-overlay-form');
        $form.css('top', '0');
        $form.css('left', '0');
        $selector.off('click', hide_vps_selector);
    }
    rn_js.hide_vps_selector = hide_vps_selector;

    function save_vps_selector() {
        var data = {
            vps: rn_common.get_vps_selection()
        }
        var pars = $('#vps-form').serializeArray();
        for (var i = 0; i < pars.length; i++) {
            data[pars[i]["name"]] = pars[i]["value"];
        }
        rn_common.send_request('sef_vps', data, rn_js.save_ok, rn_js.save_fail);
    }
    rn_js.save_vps_selector = save_vps_selector;

    function save_ok(res) {
        $('#vps-'+res.singer_id+'-'+res.song_id).html(res.vps);
        hide_vps_selector();
        rn_table.set_selected_rows([$('#rntr-'+res.singer_id)]);
        rn_table.update_table();
    }
    rn_js.save_ok = save_ok;

    function save_fail(msg) {
        alert(msg);
    }
    rn_js.save_fail = save_fail;

    function edit_singer(name, id) {
        var pos = $('#rntr-'+id).children('.singer-pos').html();
        var pvp = $('#rntr-'+id).children('.singer-pvp').html();
        var dirlock = $('#rntr-'+id).data('dirlock') == '1';
        var data = {
            'singer_id':id, 
            is_singer:pos.includes('s'),
            is_nt:pos.includes('n'),
            is_dir:pos.includes('d'),
            is_admin:pos.includes('a'), 
            primary_vp:pvp,
            dir_lock:dirlock
        };
        rn_sef.edit_singer(name, data);
    }
    rn_js.edit_singer = edit_singer;

    function download_songs() {
        var csv = "Song,Director,Start,End,Voice Parts\r\n";
        $.each(rn_songs, function() {
            var song = [];
            for (var i = 1; i < 6; i++) {
                var cell = this[i];
                if (i == 2)
                    cell = rn_dirs[cell];
                cell = cell.toString();
                if (cell.includes(","))
                    cell = "\"" + cell.replace("\"", "\\\\\"") + "\"";
                song.push(cell);
            }
            csv += song.join(",") + "\r\n";
        });

        var download_link = document.createElement("a");
        var blob = new Blob(["\ufeff", csv]);
        var url = URL.createObjectURL(blob);
        download_link.href = url;

        var dt = new Date();
        var yr = dt.getFullYear().toString();
        var mo = ("0" + (dt.getMonth() + 1)).slice(-2);
        var dy = ("0" + dt.getDate()).slice(-2);
        var hr = ("0" + dt.getHours()).slice(-2);
        var mn = ("0" + dt.getMinutes()).slice(-2);
        var date = yr + mo + dy + "_" + hr + mn;
        download_link.download = "RN_Song_List_" + date + ".csv";

        document.body.append(download_link);
        setTimeout(function() {
            download_link.click();
            document.body.removeChild(download_link);
        }, 1000);
    }
    rn_js.download_songs = download_songs;
}
rn_js(jQuery);
