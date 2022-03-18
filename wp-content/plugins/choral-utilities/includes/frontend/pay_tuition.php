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
        $html = '
            <div class="title18">Pay 2022 Tuition</div>
            <hr>
            <div class="ariel12it"><i>
            You can make your 2022 Tuition payments online using a credit/debit card, Venmo, or a PayPal account.
            Consort Chorale is charged an additional handling fee for all online payments including those from Venmo.  If
            you pay by Venmo, it will say "No fees no matter how you pay", but that only means that you will not be charged
            any additional fees at your end.  Consort is still charged fees for payments through Venmo.
            This handling fee is automatically included in your payment, and can be seen in the dropdown below.</i><br><br>
            You can avoid handling fees by paying by check to Consort Chorale, Inc. at this address:
            </div>
            <div style="line-height:1.2"><b>Consort Chorale, Inc.<br>
                P.O. Box 9212<br>
                San Rafael, CA 94912</b></div><br>
            <div style="margin:0 20px">
            If you have previously paid for one of the cancelled Workshops, you can donate its $50 payment by selecting one of the 
            first three full payment options in the dropdown.  Or you can use that $50 payment toward your 2022 Tuition by selecting one of the last two "using Workshop credit"
            options below.<br><br>
            To pay online:<ol><li>Select which payment you are making from the dropdown.</li>
            <li>Choose your method of payment by clicking one of the payment buttons</li></ol>
                <br>';

        // Code from PayPal with minor modifications:  USD is changed to $, and "Pay Later" is disabled.

        $html .= '
        <div id="smart-button-container">
      <div style="text-align: center;">
        <div style="margin-bottom: 1.25rem;">
          <p>2022 Tuition Payment</p>
          <select id="item-options"><option value="Pay Full Tuition ($275)" price="281.08">Pay Full Tuition ($275) - $281.08</option><option value="Pay Deposit ($100)" price="102.53">Pay Deposit ($100) - $102.53</option><option value="Remaining Balance ($175)" price="179.05">Remaining Balance ($175) - $179.05</option><option value="Full Tuition, using Workshop credit ($225)" price="230.07">Full Tuition, using Workshop credit ($225) - $230.07</option><option value="Remaining Balance, using Workshop credit ($125)" price="128.04">Remaining Balance, using Workshop credit ($125) - $128.04</option></select>
          <select style="visibility: hidden" id="quantitySelect"></select>
        </div>
      <div id="paypal-button-container"></div>
      </div>
    </div>
    <script src="https://www.paypal.com/sdk/js?client-id=ASnRtUr46sPeQxzML49uOuX0d-vO1ug7kEBuIU3PCzm3-Jzu0LzlAxFfCkVYslws5_ggi7lWkm-01bZO&enable-funding=venmo&disable-funding=paylater&currency=USD" data-sdk-integration-source="button-factory"></script>
    <script>
      function initPayPalButton() {
        var shipping = 0;
        var itemOptions = document.querySelector("#smart-button-container #item-options");
    var quantity = parseInt();
    var quantitySelect = document.querySelector("#smart-button-container #quantitySelect");
    if (!isNaN(quantity)) {
      quantitySelect.style.visibility = "visible";
    }
    var orderDescription = \'2022 Tuition Payment\';
    if(orderDescription === \'\') {
      orderDescription = \'Item\';
    }
    paypal.Buttons({
      style: {
        shape: \'pill\',
        color: \'gold\',
        layout: \'vertical\',
        label: \'pay\',
        
      },
      createOrder: function(data, actions) {
        var selectedItemDescription = itemOptions.options[itemOptions.selectedIndex].value;
        var selectedItemPrice = parseFloat(itemOptions.options[itemOptions.selectedIndex].getAttribute("price"));
        var tax = (0 === 0 || false) ? 0 : (selectedItemPrice * (parseFloat(0)/100));
        if(quantitySelect.options.length > 0) {
          quantity = parseInt(quantitySelect.options[quantitySelect.selectedIndex].value);
        } else {
          quantity = 1;
        }

        tax *= quantity;
        tax = Math.round(tax * 100) / 100;
        var priceTotal = quantity * selectedItemPrice + parseFloat(shipping) + tax;
        priceTotal = Math.round(priceTotal * 100) / 100;
        var itemTotalValue = Math.round((selectedItemPrice * quantity) * 100) / 100;

        return actions.order.create({
          purchase_units: [{
            description: orderDescription,
            amount: {
              currency_code: \'USD\',
              value: priceTotal,
              breakdown: {
                item_total: {
                  currency_code: \'USD\',
                  value: itemTotalValue,
                },
                shipping: {
                  currency_code: \'USD\',
                  value: shipping,
                },
                tax_total: {
                  currency_code: \'USD\',
                  value: tax,
                }
              }
            },
            items: [{
              name: selectedItemDescription,
              unit_amount: {
                currency_code: \'USD\',
                value: selectedItemPrice,
              },
              quantity: quantity
            }]
          }]
        });
      },
      onApprove: function(data, actions) {
        return actions.order.capture().then(function(orderData) {
          
          // Full available details
          console.log(\'Capture result\', orderData, JSON.stringify(orderData, null, 2));

          // Show a success message within this page, e.g.
          const element = document.getElementById(\'paypal-button-container\');
          element.innerHTML = \'\';
          element.innerHTML = \'<h3>Thank you for your payment!</h3>\';

          // Or go to another URL:  actions.redirect(\'thank_you.html\');

        });
      },
      onError: function(err) {
        console.log(err);
      },
    }).render(\'#paypal-button-container\');
  }
  initPayPalButton();
    </script>
        ';

        return $html;
    }


}
