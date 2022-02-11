/**
 * Created by joncu on 4/10/19.
 */
function cu_js($) {
    var form_dirty = false;
    var rnotes = false;

    $(document).ready(function () {
        $('.action-warning').on('click', function (e) {
            if (!confirm('Are you sure you want to delete all this data?')) {
                e.preventDefault();
            }
        });

        // This will tell us if the RNotes are on this tab
        rnotes = $('.sl-table').length;

        $('#cu-settings-form').submit(function () {
            if (rnotes) {
                cu_js.readSongList(true);
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
            if (form_dirty) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });
    });

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
        row += drawButton(id) + drawSong(id, song) + drawDir(id, song)
                + drawMeasures(id, song) + drawVoiceParts(id, song);
        row += '</tr>';
        return row;
    }

    function drawButton(id) {
        return '<td><button type="button" onclick="cu_js.doAction(\'' + id + '\')">'
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

    var vparts = ['S','A','T','B'];
    function drawVoiceParts(id, song) {
        html = '<td>';
        vparts.forEach(function(vp) {
            html += '<span class="nowrap"><input type="checkbox" id="vp' + vp + id + '" value="' + vp + '"';
            if (song[rn.vp].includes(vp))
                html += ' checked';
            html += '>' + vp + '</span>&nbsp; ';
        });
        return html + '</td>';
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
    cu_js.doAction = doAction;

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
    cu_js.readSongList = readSongList;

    function readRow(id) {
        var song_id = $('#row' + id).data('song-id');
        var song = $('#song' + id).val();
        var dir = $('#dir' + id).val();
        var sm = $('#sm' + id).val();
        var em = $('#em' + id).val();

        var vps = '';
        vparts.forEach(function(vp) {
            vps += $('#vp' + vp + id).is(':checked') ? vp : '';
        });
        return [song_id, song, dir, sm, em, vps];
    }
}
cu_js(jQuery);
