@if($gateway['sms'] !== 'http' && $gateway['whatsapp'] !== 'http')
    <div class="alert alert-warning small">
        <strong>No SMS / WhatsApp gateway is connected yet.</strong> Messages are saved in the log with status "Not sent" so nothing is lost.
        To send real messages, add your provider's API details in the server's <code>.env</code> file (see README → Notifications).
        Meanwhile you can use the free <i class="bi bi-whatsapp text-success"></i> WhatsApp button on the Pending Fees page.
    </div>
@endif
