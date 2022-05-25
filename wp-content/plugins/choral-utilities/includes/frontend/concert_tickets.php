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
/**
 * Class CuConcertTickets
 * PayPal button is constructed as NonHosted:
 *     Fill in Name of Button
 *     Add Text Field "Number of Tickets"
 *     In 2nd Dropdown, uncheck Save Button at PayPal
 *     Create Button
 *     In Website code, click "Remove code protection"
 *         NOTE: this was failing July 2019
 *         Was able to rename "item_name" below and use same button.
 *         So did not create a new PP button for this shortcode.
 */
class CuConcertTickets
{
    private $price, $handling;

    public function __construct($atts)
    {
        $this->error = '';
        if (isset($atts['price']))
            $this->price = $atts['price'];
        else
            $this->error = 'price (example 30)<br>';

        if (isset($atts['handling']))
            $this->handling = $atts['handling'];
        else
            $this->error .= 'handling (example 1.25)<br>';

        if (isset($atts['title']))
            $this->title = $atts['title'];
        else
            $this->error .= 'title (example 2022 Concert Tickets)<br>';
    }

    public function html()
    {
        if ($this->error != '')
            return 'ERROR in shortcode usage.  Missing attribute(s):<br>' . $this->error;

        $html = $this->getForm();
        $html .= $this->getJS();
        return $html;
    }

    private function getForm()
    {
    return '<div style="margin:0 20px; line-height:1.3"><b>' . $this->title . '</b><br><br>To buy online, fill in the form and click "Pay Now."<br>
                    <em>PayPal will provide a receipt, and your tickets<br> will be held in "Will Call" at the door.</em><br>
                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                    <input type="hidden" name="cmd" value="_xclick">
                    <input type="hidden" name="business" value="R83LAKP9U8PDU">
                    <input type="hidden" name="lc" value="US">
                    <input type="hidden" name="item_name" value="' . $this->title . '">
                    <input type="hidden" id="payment-total" name="amount" value="1.00">
                    <input type="hidden" name="button_subtype" value="services">
                    <input type="hidden" name="no_note" value="0">
                    <input type="hidden" name="cn" value="Add special instructions to the seller:">
                    <input type="hidden" name="no_shipping" value="2">
                    <input type="hidden" name="currency_code" value="USD">
                    <input type="hidden" name="bn" value="PP-BuyNowBF:btn_paynowCC_LG.gif:NonHosted">
                    <table style="width:370px">
                        <tr>
                            <td><input type="hidden" name="on0" value="Number of Tickets">Number of Tickets:</td>
                            <td><input id="ticket-count" type="number" name="os0" min="1" max="20" value="1"></td>
                            <td><span id="tickets-total"></span></td>
                        </tr>
                        <tr>
                            <td colspan="2">Online handling fee ($' . $this->handling . '/ticket): </td>
                            <td><span id="handling-fee"></span></td>
                        </tr>
                        <tr>
                            <td colspan="2"><input type="hidden" name="on1" value="Donation $">Make an additional donation (optional): </td>
                            <td>$<input id="add-donation" type="text" name="os1" maxlength="7" style="width:75px" /></td>
                        </tr>
                        <tr>
                            <td colspan="2">Total Payment: </td>
                            <td><span id="total"></span></td>
                        </tr>
                    </table>
                    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_paynowCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                    <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                </form>
            </div>';
    }

    private function getJS()
    {
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[';

        $js .= '
        function cu_pay($) {
            $(document).ready(function () {
                $("#ticket-count").on("change", cu_pay.set_payment);
                $("#ticket-count").on("keyup", cu_pay.set_payment);
                $("#add-donation").on("keyup", cu_pay.set_payment);
                set_payment();
            });
            function set_payment() {
                var count = $("#ticket-count").val();
                if (count == "")
                    return;
                var count = parseInt(count);
                var cost = count * ' . $this->price . ';
                if (isNaN(cost) || cost < 0) {
                    alert("Please enter a valid number of tickets.");
                    $("#ticket-count").val(1);
                    return;
                }
                var handling = count * ' . $this->handling . ';
                $("#tickets-total").html(to$(cost));
                $("#handling-fee").html(to$(handling));
                
                var donate = $("#add-donation").val();
                if (donate == "")
                    donate = "0";
                donate = Number(donate); 
                if (isNaN(donate) || donate < 0) {
                    alert("Please enter a valid dollar amount for a donation.");
                    $("#add-donation").val("");
                    return;
                }
                $("#payment-total").val(cost+handling+donate);
                $("#total").html(to$(cost+handling+donate));
            }
            cu_pay.set_payment = set_payment;
            
            function to$(val) {
                return "$ " + parseFloat(val).toFixed(2);
            }
        }
        cu_pay(jQuery);
        ';

        return $js.'
        // ]]></script>';
    }

}
