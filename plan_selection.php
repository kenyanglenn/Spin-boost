<?php
require_once 'db.php';

$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: login');
    exit;
}

if ($currentUser['plan'] !== 'NONE') {
    header('Location: spin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_plan'])) {
    $plan = $_POST['selected_plan'];
    $phone = trim($_POST['payment_phone'] ?? '');
    $costs = [
        'REGULAR' => 20,
        'PREMIUM' => 50,
        'PREMIUM+' => 100
    ];
    if (empty($phone)) {
        setFlashMessage('error', 'Please enter a phone number to receive the MPESA payment prompt.');
    } elseif (isset($costs[$plan])) {
        $cost = $costs[$plan];
        $paymentResult = initiateMpesaPayment($phone, $cost, 'Spin Boost ' . $plan . ' plan');
        if ($paymentResult['success']) {
            $pdo = getPDO();
            updateUserPlan($currentUser['id'], $plan);
            if ($currentUser['referred_by']) {
                $referrerId = getReferrerId($currentUser['referred_by']);
                if ($referrerId) {
                    addReferralReward($referrerId, $plan);
                }
            }
            setFlashMessage('success', $paymentResult['message'] . ' Your plan has been activated and will be confirmed once payment is completed.');
            header('Location: spin');
            exit;
        } else {
            setFlashMessage('error', 'MPESA payment prompt could not be sent. Please try again.');
        }
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Choose Plan - Spin Boost</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="plan-container">
        <header class="plan-header">
            <h1>Choose Your Plan</h1>
            <p>Select a plan to unlock features and start playing!</p>
            <p>Your Wallet: <?php echo number_format($currentUser['wallet'], 2); ?> KES</p>
        </header>

        <?php if ($flash): ?>
            <div class="toast <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
        <?php endif; ?>

        <section class="plans-grid">
            <div class="plan-card">
                <h3>Regular</h3>
                <p class="price">20 KES</p>
                <ul>
                    <li>5 spins/day</li>
                    <li>3 word puzzles/day</li>
                </ul>
                <button type="button" class="primary-btn plan-select-btn" data-plan="REGULAR">Select Regular</button>
            </div>

            <div class="plan-card premium">
                <h3>Premium</h3>
                <p class="price">50 KES</p>
                <ul>
                    <li>20 spins/day</li>
                    <li>25 word puzzles/day</li>
                </ul>
                <button type="button" class="primary-btn plan-select-btn" data-plan="PREMIUM">Select Premium</button>
            </div>

            <div class="plan-card premium-plus">
                <h3>Premium+</h3>
                <p class="price">100 KES</p>
                <ul>
                    <li>Unlimited spins</li>
                    <li>Unlimited puzzles</li>
                </ul>
                <button type="button" class="primary-btn plan-select-btn" data-plan="PREMIUM+">Select Premium+</button>
            </div>
        </section>

        <footer class="plan-footer">
            <a href="spin" class="secondary-btn">Back to Dashboard</a>
        </footer>
    </div>

    <div class="modal-overlay" id="planModal">
        <div class="modal-card">
            <button class="close-btn" id="closePlanModal">×</button>
            <div class="modal-content">
                <h2>Confirm Plan Payment</h2>
                <p>Enter your phone number to receive the MPESA payment prompt for the selected plan.</p>
                <form method="post" id="planPaymentForm" class="topup-form">
                    <input type="hidden" name="selected_plan" id="selectedPlan" value="">
                    <label for="payment_phone">Phone number</label>
                    <input type="tel" id="payment_phone" name="payment_phone" placeholder="07XXXXXXXX" required>
                    <label for="payment_amount">Amount (KES)</label>
                    <input type="number" id="payment_amount" name="payment_amount" readonly>
                    <button type="submit" class="primary-btn">Send MPESA Prompt</button>
                </form>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // Plan payment handler
        const planButtons = document.querySelectorAll('.plan-select-btn');
        const planModal = document.getElementById('planModal');
        const closePlanModal = document.getElementById('closePlanModal');
        const selectedPlanInput = document.getElementById('selectedPlan');
        const paymentAmountInput = document.getElementById('payment_amount');
        const planPaymentForm = document.getElementById('planPaymentForm');
        const paymentPhoneInput = document.getElementById('payment_phone');

        planButtons.forEach(button => {
            button.addEventListener('click', () => {
                const plan = button.dataset.plan;
                const costs = { REGULAR: 20, PREMIUM: 50, 'PREMIUM+': 100 };
                if (!planModal || !selectedPlanInput || !paymentAmountInput) return;
                selectedPlanInput.value = plan;
                paymentAmountInput.value = costs[plan] ?? 0;
                planModal.classList.add('active');
            });
        });

        closePlanModal?.addEventListener('click', () => planModal?.classList.remove('active'));

        // Handle plan payment form submission
        if (planPaymentForm) {
            planPaymentForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const plan = selectedPlanInput.value;
                const phone = paymentPhoneInput.value.trim();
                const amount = parseFloat(paymentAmountInput.value);

                // Validate
                if (!plan || !phone || !amount) {
                    showToast('Please fill all fields', 'error');
                    return;
                }

                if (!/^254[0-9]{9}$/.test(phone)) {
                    showToast('Invalid phone format. Use 254712345678', 'error');
                    return;
                }

                // Show loading state
                const submitBtn = planPaymentForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing...';

                try {
                    // Call plan payment handler
                    const response = await fetch('plan_payment_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            plan: plan,
                            phone: phone,
                            amount: amount
                        })
                    });

                    const result = await response.json();

                    if (result.success && result.payment_link) {
                        // Close modal and redirect to payment
                        planModal.classList.remove('active');
                        window.location.href = result.payment_link;
                    } else {
                        throw new Error(result.message || 'Failed to initiate payment');
                    }
                } catch (error) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    showToast(error.message || 'Payment initiation failed. Please try again.', 'error');
                    console.error('Plan payment error:', error);
                }
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', event => {
            if (event.target === planModal) planModal.classList.remove('active');
        });
    </script>
</body>
</html>