<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

// Note: this needed to be done with a shortcode because it requires
// javascript.
class CuPayTuition
{
    public function __construct()
    {
    }

    public function html()
    {
        $html = $this->getForm();
        $html .= $this->getJS();
        return $html;
    }

    private function getForm()
    {
    return '
            <div class="title18">Pay 2020 Tuition</div>
            <hr>
            <div class="ariel12it">
            You can make your 2020 Tuition payments online using a credit/debit card or a PayPal account.
            Consort Chorale is charged an additional handling fee for online payments, which is shown
            below and added to your payment.<br><br>
            You can avoid handling fees by paying by check to Consort Chorale, Inc. at this address:
            </div>
            <div style="line-height:1.2"><b>Consort Chorale, Inc.<br>
                P.O. Box 9212<br>
                San Rafael, CA 94912</b></div><br>
            <div style="margin:0 20px">To pay online, fill in the form below, then click "Pay Now."<br>
                <br>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="UQDXV2V4P6BSY">
<table class="tuition" style="max-width:300px">
    <tr>
        <td colspan="3"><i>Select type of payment:</i></td>
    </tr>
    <tr>
        <td><select id="paymentType">
                <option value="275">Single payment, in full</option>
                <option value="100">Deposit</option>
                <option value="175">Remaining balance</option>
            </select>
        </td>
        <td><span id="typeAmount" class="floatright"></span></td>
    </tr>
    <tr>
        <td class="radio pay-tuition-radio"><i> for: </i>
            <span>
                <input type="radio" name="count" value="1" checked> One
                <input type="radio" name="count" value="2"> Two
            </span>
        </td>
    </tr>
    <tr>
        <td class="sumline">Total Tuition:</td>
        <td class="sumline"><span id="totalTuition" class="floatright"></span></td>
    </tr>
    <tr>
        <td class="spacer">Online handling fee:</td>
        <td><span id="handling" class="floatright"></span></td>
    </tr>
    <tr>
        <td class="sumline"><input type="hidden" name="on0" value="Payment options">Total Payment:</td>
        <td class="sumline"><select style="display:none" id="ppTotal" name="os0">
                <option value="Full Tuition (1)">$281.49</option>
                <option value="Deposit (1)">$102.56</option>
                <option value="Balance (1)">$179.24</option>
                <option value="Full Tuition (2)">$562.68</option>
                <option value="Deposit (2)">$204.81</option>
                <option value="Balance (2)">$358.18</option>
            </select>
            <span id="total" class="floatright"></span>
        </td>
    </tr>
</table>
<input type="hidden" name="currency_code" value="USD">
<input type="image" style="border:none; padding-top:10px" src="https://www.paypalobjects.com/en_US/i/btn/btn_paynowCC_LG.gif" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>';
    }

    private function getJS()
    {
        // NOTE: The sTotal payments are being calculated here - but they are fixed values.
        // These values have to be already recorded at PayPal when the button is created,
        // and they appear in the form above, which originated from PP, but modified
        // to have the calculations revealed to the user.
        // The actual PayPal dropdown is not exposed here.  Instead - the user sets it
        // indirectly from the calculations.  To ensure that the correct amount is being
        // sent to PayPal - the visible Total value shown to the user is gotten from the
        // hidden PP dropdown's selected option.

        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[';

        $js .= '
        function cu_pay($) {
            $(document).ready(function () {
                $("#paymentType").on("change", cu_pay.setPayment);
                $("input[name=\'count\']").change(cu_pay.setPayment);
                setPayment();
            });
            function setPayment() {
                var item = $("#paymentType").val();
                var count = $("input[name=\'count\']:checked").val();
                var tuition = item * count;
                var total = (tuition + 0.30) / 0.978;
                var handling = total - tuition;
                
                $("#typeAmount").text(to$(item));
                $("#totalTuition").text(to$(tuition));
                $("#handling").text(to$(handling));
                
                var sTotal = to$(total);
                $("#ppTotal option").each(function() {
                    if ($(this).text() == sTotal) {
                        $(this).prop("selected", true);
                        return false;
                    }
                });
                $("#total").text($("#ppTotal option:selected").text());
            }
            cu_pay.setPayment = setPayment;
            
            function to$(val) {
                return "$" + parseFloat(val).toFixed(2);
            }
        }
        cu_pay(jQuery);
        ';

        return $js.'
        // ]]></script>';
    }

}
