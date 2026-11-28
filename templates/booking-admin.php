<?php
// SPDX-License-Identifier: AGPL-3.0-only
script('certexi_sync','booking-admin');
?>
<section class="section">
    <h2><?php p($l->t('Certexi booking')); ?></h2>
    <form id="certexi-booking-origin">
        <label for="certexi-booking-url"><?php p($l->t('Trusted Certexi booking origin')); ?></label>
        <input type="url" id="certexi-booking-url" value="<?php p($_['origin']); ?>" placeholder="https://certexi.example.com">
        <button type="submit"><?php p($l->t('Save')); ?></button>
        <p><?php p($l->t('Use the HTTPS address of the Certexi installation that holds your reservations. Clear it to stop calendar delivery.')); ?></p>
        <p role="status"></p>
    </form>
</section>
