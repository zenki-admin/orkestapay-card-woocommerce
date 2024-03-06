<?php
if (!defined('ABSPATH')) {
    exit();
}
// Exit if accessed directly
?>

<fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent; margin-bottom: 10px;">    
    <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>    

    <p class="orkesta-cards-copy"><?php esc_html_e('Accepted cards', 'orkestapay-card'); ?></p>

    <div class="orkesta-credit-cards">
        <?php foreach ($this->brands as $item): ?>   
            <img alt="<?php echo esc_attr($item->brand); ?>" src="<?php echo esc_url($item->logo); ?>" />
        <?php endforeach; ?>           
    </div>

    <p><?php echo esc_html($this->description); ?></p>

    <div class="form-row form-row-wide">
        <label for="orkesta-holder-name"><?php esc_html_e('Holder Name', 'orkestapay-card'); ?> <span class="required">*</span></label>
        <div class="wc-orkesta-field">
            <input id="orkesta-holder-name" class="input-text" type="text" autocomplete="off" placeholder="<?php esc_html_e('Holder Name', 'orkestapay-card'); ?>" />
        </div>
    </div>
    <div class="form-row form-row-wide">
        <label for="orkesta-card-number"><?php esc_html_e('Card Number', 'orkestapay-card'); ?> <span class="required">*</span></label>
        <div class="wc-orkesta-field">
            <input id="orkesta-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••"  />
        </div>
    </div>

    <div class="form-row form-row-wide">
        <label for="orkesta-card-expiry"><?php esc_html_e('Expiry Date', 'orkestapay-card'); ?> <span class="required">*</span></label>
        <div class="wc-orkesta-field">
            <input id="orkesta-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="MM / YYYY" />
        </div>
    </div>
    <div class="form-row form-row-wide">
        <label for="orkesta-card-cvc"><?php esc_html_e('Card Code (CVC)', 'orkestapay-card'); ?> <span class="required">*</span></label>
        <div class="wc-orkesta-field">
            <input id="orkesta-card-cvc" name="orkesta_card_cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="CVV" />
        </div>
    </div>    

    <input type="hidden" name="orkesta_device_session_id" id="orkesta_device_session_id" value="" />
    <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>    
    
    <div class="clear"></div>    
</fieldset>