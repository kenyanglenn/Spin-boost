const spinWheel = document.getElementById('spinWheel');
const spinNowBtn = document.getElementById('spinNow');
const spinStakeInput = document.getElementById('spinStake');
const spinResultText = document.getElementById('spinResultText');
const reelRow = document.getElementById('reelRow');
const newPuzzleBtn = document.getElementById('newPuzzle');
const submitPuzzleBtn = document.getElementById('submitPuzzle');
const puzzleStakeInput = document.getElementById('puzzleStake');
const puzzleAnswerInput = document.getElementById('puzzleAnswer');
const puzzleTimer = document.getElementById('puzzleTimer');
const puzzleMultiplier = document.getElementById('puzzleMultiplier');
const puzzleResultText = document.getElementById('puzzleResultText');
const spinLimitText = document.getElementById('spinLimitText');
const puzzleLimitText = document.getElementById('puzzleLimitText');
const walletDisplay = document.querySelector('.top-bar h1');
const openTopup = document.getElementById('openTopup');
const topupModal = document.getElementById('topupModal');
const closeTopup = document.getElementById('closeTopup');
const copyReferral = document.getElementById('copyReferral');
const globalPopup = document.getElementById('globalPopup');

const initialWallet = parseFloat(document.body.dataset.wallet || '0');
let currentWallet = initialWallet;
function updateWalletDisplay(newValue) {
    currentWallet = parseFloat(newValue);
    if (walletDisplay) walletDisplay.textContent = `${currentWallet.toFixed(2)} KES`;
    document.body.dataset.wallet = currentWallet.toFixed(2);
}

let spinInProgress = false;
let puzzleState = { timer: null, remaining: 0 };
let currentLetters = [];
let currentMultiplier = 1.5;

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    const container = document.querySelector('.app-shell') || document.body;
    container.prepend(toast);
    setTimeout(() => toast.remove(), 3600);
}

function showPopup(title, message, type = 'info') {
    if (!globalPopup) {
        showToast(message, type === 'error' ? 'error' : 'success');
        return;
    }
    globalPopup.querySelector('.popup-title').textContent = title;
    globalPopup.querySelector('.popup-message').textContent = message;
    globalPopup.classList.add('active', type);
}

function hidePopup() {
    if (!globalPopup) return;
    globalPopup.classList.remove('active', 'success', 'error', 'info');
}

function updateUsageCounts(data) {
    if (data.spinCount !== undefined && data.spinLimit !== undefined) {
        spinLimitText.textContent = `Remaining spins today: ${data.spinLimit - data.spinCount} / ${data.spinLimit}`;
        spinNowBtn.disabled = data.spinCount >= data.spinLimit;
    }
    if (data.puzzleCount !== undefined && data.puzzleLimit !== undefined) {
        puzzleLimitText.textContent = `Remaining puzzles today: ${data.puzzleLimit - data.puzzleCount} / ${data.puzzleLimit}`;
        const limitReached = data.puzzleCount >= data.puzzleLimit;
        newPuzzleBtn.disabled = limitReached;
        submitPuzzleBtn.disabled = limitReached || submitPuzzleBtn.disabled;
    }
}

function secureRandom() {
    if (window.crypto && window.crypto.getRandomValues) {
        const array = new Uint32Array(1);
        window.crypto.getRandomValues(array);
        return array[0] / 0xFFFFFFFF;
    }
    return Math.random();
}

function rotateWheelToSegment(index, onComplete) {
    const segmentAngle = 360 / 10;
    const extraSpins = 6 + Math.floor(secureRandom() * 6);
    const target = 360 * extraSpins + (index * segmentAngle) + segmentAngle / 2;
    const duration = 2.8 + secureRandom() * 0.8;
    const easing = `cubic-bezier(${0.18 + secureRandom() * 0.1}, ${0.8 + secureRandom() * 0.08}, ${0.25 + secureRandom() * 0.08}, 1)`;

    spinWheel.style.transition = `transform ${duration.toFixed(2)}s ${easing}`;
    spinWheel.style.transform = `rotate(-${target}deg)`;

    const handleTransitionEnd = () => {
        spinWheel.style.transition = 'none';
        const normalized = target % 360;
        spinWheel.style.transform = `rotate(-${normalized}deg)`;
        if (typeof onComplete === 'function') {
            onComplete();
        }
    };

    spinWheel.addEventListener('transitionend', handleTransitionEnd, { once: true });
}

function fetchSpin() {
    if (spinInProgress) return;
    const stake = parseFloat(spinStakeInput.value);
    if (isNaN(stake) || stake < 10) {
        showPopup('Invalid stake', 'Enter a valid stake of at least 10 KES.', 'error');
        return;
    }
    spinInProgress = true;
    spinNowBtn.disabled = true;
    fetch('spin_logic.php', {
        method: 'POST',
        body: new URLSearchParams({ stake }),
    }).then(res => res.json()).then(data => {
        updateUsageCounts(data);
        if (!data.success) {
            showPopup('Spin failed', data.message, 'error');
            spinInProgress = false;
            spinNowBtn.disabled = false;
            return;
        }
        rotateWheelToSegment(data.segmentIndex, () => {
            const newWallet = parseFloat(data.wallet).toFixed(2);
            updateWalletDisplay(newWallet);
            spinResultText.textContent = `Result: ${data.segmentLabel} — multiplier ×${data.multiplier}. Wallet: ${newWallet} KES.`;
            const title = data.multiplier > 0 ? 'You Won!' : 'No Prize This Time';
            const message = data.multiplier > 0
                ? `You landed on ${data.segmentLabel} and earned ${parseFloat(data.winAmount).toFixed(2)} KES.`
                : 'The wheel landed on a zero multiplier. Try again!';
            showPopup(title, `${message} Wallet balance: ${newWallet} KES.`, data.multiplier > 0 ? 'success' : 'info');
            spinInProgress = false;
            spinNowBtn.disabled = false;
        });
        spinResultText.textContent = 'Spinning...';
    }).catch(() => {
        showPopup('Network error', 'Unable to contact server. Try again later.', 'error');
        spinInProgress = false;
        spinNowBtn.disabled = false;
    });
}

function buildPuzzleReels(wordLength) {
    reelRow.innerHTML = '';
    for (let i = 0; i < wordLength; i++) {
        const reel = document.createElement('div');
        reel.className = 'reel';
        reel.textContent = 'A';
        reelRow.appendChild(reel);
    }
}

function animatePuzzleLetters(finalLetters) {
    const reels = [...document.querySelectorAll('.reel')];
    reels.forEach((reel, index) => {
        let count = 0;
        const interval = setInterval(() => {
            const letter = String.fromCharCode(65 + Math.floor(Math.random() * 26));
            reel.textContent = letter;
            count += 1;
            if (count > 12 + index * 6) {
                clearInterval(interval);
                reel.textContent = finalLetters[index] || '';
            }
        }, 60);
    });
}

function startPuzzleTimer(seconds) {
    if (puzzleState.timer) {
        clearInterval(puzzleState.timer);
    }
    puzzleState.remaining = seconds;
    puzzleTimer.textContent = `Time: 00:${seconds.toString().padStart(2, '0')}`;
    puzzleState.timer = setInterval(() => {
        puzzleState.remaining -= 1;
        if (puzzleState.remaining <= 0) {
            clearInterval(puzzleState.timer);
            puzzleState.timer = null;
            puzzleTimer.textContent = 'Time: 00:00';
            showPopup('Time expired', 'Your puzzle time has run out. Submit to reveal the result or play again.', 'error');
            submitPuzzleBtn.disabled = true;
            puzzleAnswerInput.disabled = true;
            return;
        }
        puzzleTimer.textContent = `Time: 00:${puzzleState.remaining.toString().padStart(2, '0')}`;
    }, 1000);
}

function fetchNewPuzzle(stake) {
    const body = new URLSearchParams({ action: 'generate' });
    if (stake !== undefined) {
        body.set('stake', stake);
    }
    fetch('puzzle_logic.php', {
        method: 'POST',
        body,
    }).then(res => res.json()).then(data => {
        updateUsageCounts(data);
        if (!data.success) {
            showPopup('Puzzle error', data.message, 'error');
            return;
        }
        currentLetters = data.letters;
        currentMultiplier = data.multiplier;
        const newWallet = parseFloat(data.wallet).toFixed(2);
        updateWalletDisplay(newWallet);
        puzzleMultiplier.textContent = `Reward ×${currentMultiplier}`;
        puzzleAnswerInput.value = '';
        buildPuzzleReels(data.letters.length);
        animatePuzzleLetters(data.letters);
        startPuzzleTimer(data.timeLimit);
        puzzleResultText.textContent = 'Guess the word and submit before the timer reaches zero.';
    }).catch(() => {
        showPopup('Connection issue', 'Unable to load puzzle. Check your internet connection.', 'error');
    });
}

function submitPuzzle() {
    const answer = puzzleAnswerInput.value.trim();
    if (!answer) {
        showPopup('Enter answer', 'Please type your guess before submitting.', 'error');
        return;
    }
    const params = new URLSearchParams({ action: 'submit', answer });
    fetch('puzzle_logic.php', { method: 'POST', body: params }).then(res => res.json()).then(data => {
        updateUsageCounts(data);
        if (!data.success) {
            showPopup('Puzzle result', data.message, 'error');
            return;
        }
        const title = data.correct ? 'Great job!' : 'Try again';
        updateWalletDisplay(parseFloat(data.wallet).toFixed(2));
        showPopup(title, `${data.message} Wallet: ${parseFloat(data.wallet).toFixed(2)} KES.`, data.correct ? 'success' : 'error');
        puzzleResultText.textContent = data.message;
        if (puzzleState.timer) {
            clearInterval(puzzleState.timer);
            puzzleState.timer = null;
        }
        puzzleAnswerInput.disabled = true;
        submitPuzzleBtn.disabled = true;
    }).catch(() => {
        showPopup('Submission failed', 'Unable to submit puzzle. Try again shortly.', 'error');
    });
}

spinNowBtn?.addEventListener('click', event => {
    event.preventDefault();
    fetchSpin();
});

newPuzzleBtn?.addEventListener('click', event => {
    event.preventDefault();
    const modal = document.getElementById('puzzleStartModal');
    if (modal) {
        modal.classList.add('active');
    } else {
        fetchNewPuzzle();
    }
});

submitPuzzleBtn?.addEventListener('click', event => {
    event.preventDefault();
    submitPuzzle();
});

openTopup?.addEventListener('click', () => topupModal?.classList.add('active'));
closeTopup?.addEventListener('click', () => topupModal?.classList.remove('active'));

// Payment form handler
const topupForm = document.getElementById('topupForm');
const topupSubmitBtn = document.getElementById('topupSubmitBtn');
const topupError = document.getElementById('topupError');
const topupProcessing = document.getElementById('topupProcessing');

if (topupForm) {
    topupForm.addEventListener('submit', (e) => {
        const phone = document.getElementById('topup_phone').value.trim();
        const amount = parseFloat(document.getElementById('topup_amount').value);

        if (!phone || !amount) {
            e.preventDefault();
            showToast('Please fill all fields', 'error');
            return;
        }

        if (!/^(?:07[0-9]{8}|2547[0-9]{8})$/.test(phone)) {
            e.preventDefault();
            showToast('Invalid phone format. Use 07XXXXXXXX or 2547XXXXXXXX', 'error');
            return;
        }

        if (amount < 50 || amount > 100000) {
            e.preventDefault();
            showToast('Amount must be between 50 and 100,000 KES', 'error');
            return;
        }
    });
}

const puzzleStartModalElement = document.getElementById('puzzleStartModal');
const closePuzzleStart = document.getElementById('closePuzzleStart');
const puzzleStartForm = document.getElementById('puzzleStartForm');
const puzzleModalStake = document.getElementById('puzzle_modal_stake');
const planModal = document.getElementById('planModal');
const closePlanModal = document.getElementById('closePlanModal');
const selectedPlanInput = document.getElementById('selectedPlan');
const paymentAmountInput = document.getElementById('payment_amount');
const planButtons = document.querySelectorAll('.plan-select-btn');
const closeGlobalPopup = document.getElementById('closeGlobalPopup');

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
closePuzzleStart?.addEventListener('click', () => puzzleStartModalElement?.classList.remove('active'));
closeGlobalPopup?.addEventListener('click', hidePopup);

window.addEventListener('click', event => {
    if (event.target === topupModal) topupModal.classList.remove('active');
    if (event.target === puzzleStartModalElement) puzzleStartModalElement.classList.remove('active');
    if (event.target === planModal) planModal.classList.remove('active');
    if (event.target === globalPopup) hidePopup();
});

puzzleStartForm?.addEventListener('submit', event => {
    event.preventDefault();
    const stake = parseFloat(puzzleModalStake?.value || 0);
    if (isNaN(stake) || stake < 10) {
        showPopup('Invalid stake', 'Enter at least 10 KES to play.', 'error');
        return;
    }
    if (stake > currentWallet) {
        showPopup('Insufficient funds', 'Your wallet does not cover that stake.', 'error');
        return;
    }
    puzzleStakeInput.value = stake;
    puzzleStakeInput.disabled = false;
    puzzleAnswerInput.disabled = false;
    submitPuzzleBtn.disabled = false;
    puzzleStartModalElement?.classList.remove('active');
    showPopup('Puzzle started', `Your stake is ${stake.toFixed(2)} KES. Good luck!`, 'success');
    fetchNewPuzzle(stake);
});

if (copyReferral) {
    copyReferral.addEventListener('click', () => {
        const referralInput = document.querySelector('.referral-box input');
        referralInput.select();
        navigator.clipboard.writeText(referralInput.value).then(() => {
            showToast('Referral link copied.', 'success');
        });
    });
}


const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');
const closeMenu = document.getElementById('closeMenu');
const menuItems = document.querySelectorAll('.menu-item');
const menuSections = document.querySelectorAll('.menu-section');
const faqQuestions = document.querySelectorAll('.faq-question');
const faqAnswers = document.querySelectorAll('.faq-answer');

hamburger?.addEventListener('click', () => {
    mobileMenu?.classList.add('active');
});

closeMenu?.addEventListener('click', () => {
    mobileMenu?.classList.remove('active');
});

menuItems.forEach(item => {
    item.addEventListener('click', () => {
        const section = item.dataset.section;
        menuSections.forEach(sec => sec.classList.remove('active'));
        document.getElementById(section + '-section')?.classList.add('active');
    });
});

faqQuestions.forEach((question, index) => {
    question.addEventListener('click', () => {
        const answer = faqAnswers[index];
        answer.classList.toggle('active');
    });
});

window.addEventListener('click', event => {
    if (event.target === mobileMenu) {
        mobileMenu.classList.remove('active');
    }
});
