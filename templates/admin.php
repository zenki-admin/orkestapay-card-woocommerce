<?php
if (!defined('ABSPATH')) {
    exit();
}
// Exit if accessed directly
?>

 <div class="orkesta-admin-container">
    <div class="orkesta-header">
        <img class="orkesta-img" alt="OrkestaPay" height="40" src="<?php echo esc_url($this->logo); ?>"/>
        <div class="orkesta-copy">                
            <p><?php echo esc_html_e('Unlocking Payment Ecosystem', 'orkestapay-card'); ?></p>
        </div>
    </div>
    <div class="orkesta-form-container">                
        <p class="instructions"><?php echo esc_html_e('For more information about plugin configuration ', 'orkestapay-card'); ?><a href="https://orkestapay.com" target="_blank"><?php echo esc_html_e(
    'click here',
    'orkestapay-card'
); ?>.</a></p>
        <hr>
        <table class="form-table">
            <input autocomplete="false" aria-autocomplete="none" name="hidden" type="text" style="display: none;" />
            <?php $this->generate_settings_html(); ?>                
        </table> 
    </div>
</div>