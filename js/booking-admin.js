// SPDX-License-Identifier: AGPL-3.0-only
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('certexi-booking-origin');
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const status = form.querySelector('[role="status"]');
        try {
            const response = await fetch(OC.linkToOCS('apps/certexi_sync/api/v1', 2) + 'booking/origin?format=json', {
                method: 'POST',
                headers: {'Content-Type':'application/json','OCS-APIRequest':'true','requesttoken':OC.requestToken},
                body: JSON.stringify({origin: form.querySelector('input').value}),
            });
            if (!response.ok || (await response.json()).ocs.meta.status !== 'ok') throw new Error();
            status.textContent = t('certexi_sync','Saved');
        } catch {
            status.textContent = t('certexi_sync','Could not save booking origin');
        }
    });
});
