/**
 * Created by joncu on 4/22/19.
 */
function rn_common($) {

    // ========= AJAX Requests =========
    // Pages that use this must send the ajax array to JS
    // via RehearsalNotes::get_ajax_JS(get_class($this))
    function send_request(n_par, data, success_fcn, fail_fcn) {
        if (typeof rn_req === 'undefined') {
            fail_fcn('ERROR 1015: Notify Web Admin');
            return;
        }
        var reqs = rn_req.length;
        var request = null;
        for (var i = 0; i < reqs; i++) {
            if (rn_req[i].n_par == n_par) {
                request = rn_req[i];
                break;
            }
        }
        if (request == null) {
            fail_fcn('ERROR 1016: Notify Web Admin');
            return;
        }
        data['action'] = request.action;
        data[request.n_par] = request.n_val;
        $.post(ajaxurl, data,
            function(res) {
                res.success_fcn = success_fcn;
                res.fail_fcn = fail_fcn;
                rn_common.handle_response(res);
            }
        ).fail(function() {
            fail_fcn('ERROR 1017: Notify Web Admin');
        })
    }
    rn_common.send_request = send_request;

    function handle_response(res) {
        if (res.success == false) {
            if (res.data.code == 0)
                res.fail_fcn(res.data.msg);
            else
                res.fail_fcn('ERROR '+res.data.code+': Notify Web Admin');
        } else {
            res.success_fcn(res.data);
        }
    }
    rn_common.handle_response = handle_response;

    // ========= SectionWidget Voice Parts =========

    // Duplicated in section_widget.php ...
    function get_vps_control(vps, sel='', id='', ro=false) {
        var sel_list = sel.split(',');
        var vps_list = vps.split(',');
        var show_list = [];
        var id_prefix = 'vp-';
        var option = 'vp-option';
        var is_sel = ' vp-selected"';
        if (ro) {
            var id_prefix = 'ro-';
            var option = 'ro-option';
            var is_sel = ' ro-selected"';
        }
        for (var i = 0; i < vps_list.length; i++) {
            var cls = sel_list.includes(vps_list[i]) ? is_sel : '';
            show_list.push('<span id="'+id_prefix+id+'-'+vps_list[i]+'" class="'+option+
                cls+'">'+vps_list[i]+'</span>');
        }
        return '<span>' + show_list.join(',&shy') + '</span>';
    }
    rn_common.get_vps_control = get_vps_control;

    function set_vps_selection(sel, id='') {
        $('[id^=vp-'+id+'-]').each(function() {
            var vp = $(this).attr('id').replace('vp-'+id+'-', '');
            if (sel.includes(vp))
                $(this).addClass('vp-selected');
            else
                $(this).removeClass('vp-selected');
        });
    }
    rn_common.set_vps_selection = set_vps_selection;

    function get_vps_selection(id='') {
        var vps_list = [];
        $('[id^=vp-'+id+'-]').each(function() {
            if ($(this).hasClass('vp-selected')) {
                var vp = $(this).attr('id').replace('vp-'+id+'-', '');
                vps_list.push(vp);
            }
        });
        return vps_list.join(',');
    }
    rn_common.get_vps_selection = get_vps_selection;

    function set_vps_control_events() {
        $('.vp-option').off('click', rn_common.vp_option_click);
        $('.vp-option').on('click', rn_common.vp_option_click);
    }
    rn_common.set_vps_control_events = set_vps_control_events;

    function vp_option_click(e) {
        if ($(this).hasClass('vp-selected'))
            $(this).removeClass('vp-selected');
        else
            $(this).addClass('vp-selected');

        // Hack, but works.  Needed because this is not an input.
        if (typeof rn_adtab != 'undefined')
            rn_adtab.setFormDirty();
        else if (typeof rn_stab != 'undefined')
            rn_stab.setFormDirty();
    }
    rn_common.vp_option_click = vp_option_click;

}
rn_common(jQuery);