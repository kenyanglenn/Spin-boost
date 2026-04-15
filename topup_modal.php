<div class="modal-overlay" id="topupModal">
    <div class="modal-card">
        <button class="close-btn" id="closeTopup">×</button>
        <div class="modal-content">
            <h2>Top Up Wallet</h2>
            <p>Add funds to your wallet using mobile money (M-Pesa, Airtel Money)</p>
            <form id="topupForm" class="topup-form">
                <div class="form-group">
                    <label for="topup_phone">Phone number</label>
                    <input type="tel" id="topup_phone" name="topup_phone" placeholder="254712345678" pattern="^254[0-9]{9}$" required>
                    <small style="color: #666;">Format: 254712345678 (Kenyan number)</small>
                </div>
                <div class="form-group">
                    <label for="topup_amount">Amount (KES)</label>
                    <input type="number" id="topup_amount" name="topup_amount" placeholder="e.g., 100" min="50" step="10" required>
                    <small style="color: #666;">Minimum: KES 50 | Maximum: KES 100,000</small>
                </div>
                <button type="submit" class="primary-btn" id="topupSubmitBtn">Proceed to Payment</button>
                <div id="topupError" style="display:none; color: #dc2626; margin-top: 10px; padding: 10px; background: #fee2e2; border-radius: 5px;"></div>
            </form>
            <div id="topupProcessing" style="display:none; text-align: center;">
                <div class="spinner" style="border: 4px solid #e5e7eb; border-top: 4px solid #3b82f6; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto;"></div>
                <p>Processing your deposit...</p>
            </div>
            <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        </div>
    </div>
</div>

<div class="modal-overlay" id="puzzleStartModal">
    <div class="modal-card">
        <button class="close-btn" id="closePuzzleStart">×</button>
        <div class="modal-content">
            <h2>Start Puzzle</h2>
            <p>Choose your stake and proceed to reveal the animated puzzle letters.</p>
            <form id="puzzleStartForm" class="topup-form">
                <label for="puzzle_modal_stake">Stake (KES)</label>
                <input type="number" id="puzzle_modal_stake" name="puzzle_modal_stake" placeholder="Minimum 10" min="10" step="1" value="10" required>
                <button type="submit" class="primary-btn">Proceed</button>
            </form>
        </div>
    </div>
</div>

<div class="popup-overlay" id="globalPopup">
    <div class="popup-card">
        <button class="close-btn" id="closeGlobalPopup">×</button>
        <div class="popup-content">
            <h2 class="popup-title">Notice</h2>
            <p class="popup-message"></p>
        </div>
    </div>
</div>
