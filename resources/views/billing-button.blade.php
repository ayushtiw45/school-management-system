@if(! $ready)
    <p class="text-muted mb-0">Online payment is being set up. @if($whatsapp)<a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">Contact us on WhatsApp</a>@endif</p>
@elseif(! auth()->user()->isAdmin())
    <p class="text-muted mb-0">Ask your school admin to start the plan.</p>
@else
    <div class="alert alert-danger d-none text-start" id="subErr"></div>
    @if(\App\Models\Coupon::where('is_active', true)->exists())
    <div class="mb-3 mx-auto" style="max-width:420px">
        <div class="input-group input-group-sm">
            <input id="couponCode" class="form-control text-uppercase" placeholder="Have a coupon code?" autocomplete="off">
            <button type="button" class="btn btn-outline-secondary" id="couponBtn">Apply</button>
        </div>
        <div class="small mt-1 text-start" id="couponMsg"></div>
    </div>
    @endif
    <button type="button" class="btn btn-lg px-4 fw-semibold" style="background:#FF4B32;color:#fff" id="subBtn">
        <i class="bi bi-lock"></i> {{ $trial ? 'Pay ₹'.($trialPrice + 0).' & start trial' : 'Pay ₹'.number_format($monthlyPrice).' & activate' }}
    </button>
    <div class="small text-muted mt-2">Secure payment by Razorpay · UPI Autopay / card</div>
    <form method="POST" action="{{ route('billing.verify') }}" id="subVerify" class="d-none">
        @csrf<input name="razorpay_payment_id"><input name="razorpay_subscription_id"><input name="razorpay_signature">
    </form>
    @once
    @push('scripts')
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    (function () {
        const btn = document.getElementById('subBtn'), err = document.getElementById('subErr');
        const cIn = document.getElementById('couponCode'), cBtn = document.getElementById('couponBtn'), cMsg = document.getElementById('couponMsg');
        let coupon = '';
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        if (cBtn) {
            const apply = async function () {
                cMsg.className = 'small mt-1 text-start text-muted'; cMsg.textContent = 'Checking…';
                const res = await fetch(@json(route('billing.coupon')), {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify({code: cIn.value})});
                const d = await res.json();
                coupon = d.ok ? d.code : '';
                cMsg.className = 'small mt-1 text-start ' + (d.ok ? 'text-success fw-semibold' : 'text-danger');
                cMsg.textContent = d.message || 'Error';
                if (d.ok && d.button) { btn.innerHTML = '<i class="bi bi-lock"></i> ' + d.button; }
            };
            cBtn.addEventListener('click', apply);
            cIn.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); apply(); } });
        }
        btn.addEventListener('click', async function () {
            err.classList.add('d-none'); btn.disabled = true;
            try {
                const res = await fetch(@json(route('billing.start')), {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify({coupon: coupon})});
                const data = await res.json();
                if (!res.ok) { throw new Error(data.message || 'Error'); }
                const rzp = new Razorpay({
                    key: data.key, subscription_id: data.subscription_id, name: data.name, description: data.description,
                    prefill: data.prefill, theme: {color: '#17191C'},
                    handler: function (r) {
                        const f = document.getElementById('subVerify');
                        f.razorpay_payment_id.value = r.razorpay_payment_id; f.razorpay_subscription_id.value = r.razorpay_subscription_id; f.razorpay_signature.value = r.razorpay_signature;
                        f.submit();
                    },
                    modal: {ondismiss: function () { btn.disabled = false; }}
                });
                rzp.on('payment.failed', function (r) { err.textContent = r.error.description; err.classList.remove('d-none'); btn.disabled = false; });
                rzp.open();
            } catch (ex) { err.textContent = ex.message; err.classList.remove('d-none'); btn.disabled = false; }
        });
    })();
    </script>
    @endpush
    @endonce
@endif
