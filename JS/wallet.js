// JS/wallet.js

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Wallet JS loaded');
    
    // Responsive view switcher
    function checkScreenSize() {
        const mobileView = document.querySelector('.mobile-view');
        const desktopView = document.querySelector('.desktop-view');

        if (window.innerWidth >= 1024) {
            if (mobileView) mobileView.style.display = 'none';
            if (desktopView) desktopView.style.display = 'block';
        } else {
            if (mobileView) mobileView.style.display = 'block';
            if (desktopView) desktopView.style.display = 'none';
        }
    }

    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);
});

// View transaction details - FIXED VERSION
function viewTransaction(transaction) {
    console.log('Viewing transaction:', transaction);
    
    // If transaction is passed as a string (just ID), fetch details
    if (typeof transaction === 'string') {
        fetchTransactionDetails(transaction);
        return;
    }
    
    // Format the amount based on transaction type
    const amountClass = transaction.is_credit ? 'positive' : 'negative';
    const amountPrefix = transaction.is_credit ? '+' : '-';
    
    // Build HTML for transaction details
    const html = `
        <div style="text-align: left; padding: 10px;">
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <span style="font-size: 18px; font-weight: bold;">Transaction Details</span>
                    <span style="background: ${transaction.status === 'completed' ? '#4CAF50' : '#FF9800'}; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                        ${transaction.status.toUpperCase()}
                    </span>
                </div>
                
                <div style="margin-bottom: 20px; text-align: center;">
                    <span style="font-size: 32px; font-weight: bold; color: ${transaction.is_credit ? '#4CAF50' : '#f44336'};">
                        ${amountPrefix}${transaction.formatted_amount}
                    </span>
                </div>
                
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px 0; color: #666;">Transaction ID</td>
                        <td style="padding: 10px 0; text-align: right; font-weight: 500;">${transaction.display_id || transaction.id}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px 0; color: #666;">Type</td>
                        <td style="padding: 10px 0; text-align: right; font-weight: 500; text-transform: capitalize;">${transaction.type_display}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px 0; color: #666;">Date & Time</td>
                        <td style="padding: 10px 0; text-align: right; font-weight: 500;">${transaction.date}</td>
                    </tr>
                    ${transaction.reference && transaction.reference !== 'N/A' ? `
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px 0; color: #666;">Reference</td>
                        <td style="padding: 10px 0; text-align: right; font-weight: 500;">${transaction.reference}</td>
                    </tr>
                    ` : ''}
                    ${transaction.description ? `
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px 0; color: #666;">Description</td>
                        <td style="padding: 10px 0; text-align: right; font-weight: 500;">${transaction.description}</td>
                    </tr>
                    ` : ''}
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px 0; color: #666;">Balance Before</td>
                        <td style="padding: 10px 0; text-align: right; font-weight: 500;">₦${transaction.balance_before?.toLocaleString() || '0.00'}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0; color: #666;">Balance After</td>
                        <td style="padding: 10px 0; text-align: right; font-weight: 500; color: #ff5e00;">₦${transaction.balance_after?.toLocaleString() || '0.00'}</td>
                    </tr>
                </table>
            </div>
        </div>
    `;
    
    Swal.fire({
        title: 'Transaction Details',
        html: html,
        confirmButtonColor: '#ff5e00',
        confirmButtonText: 'Close',
        width: '500px'
    });
}

// Fetch transaction details by ID
function fetchTransactionDetails(transactionId) {
    // Show loading
    Swal.fire({
        title: 'Loading...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch transaction details from API
    fetch(`SERVER/API/get_transaction.php?transaction_id=${transactionId}`)
        .then(response => response.json())
        .then(data => {
            Swal.close();
            
            if (data.success && data.transaction) {
                // Format the transaction data
                const trans = data.transaction;
                const isCredit = ['deposit', 'bonus', 'referral'].includes(trans.transaction_type);
                
                viewTransaction({
                    id: trans.id,
                    display_id: trans.reference || 'TXN-' + trans.id.substr(-8),
                    type: trans.transaction_type,
                    type_display: trans.type_display || trans.transaction_type.replace(/_/g, ' '),
                    amount: trans.amount,
                    formatted_amount: '₦' + parseFloat(trans.amount).toLocaleString(),
                    date: new Date(trans.created_at).toLocaleString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric',
                        hour: '2-digit', 
                        minute: '2-digit'
                    }),
                    status: trans.status,
                    reference: trans.reference,
                    description: trans.description,
                    balance_before: trans.balance_before,
                    balance_after: trans.balance_after,
                    is_credit: isCredit
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to load transaction details',
                    confirmButtonColor: '#ff5e00'
                });
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            
            // Fallback - show basic info
            Swal.fire({
                icon: 'info',
                title: 'Transaction',
                text: 'Transaction ID: ' + transactionId,
                confirmButtonColor: '#ff5e00'
            });
        });
}

// Add funds to wallet
function addFunds() {
    Swal.fire({
        title: 'Add Funds to Wallet',
        html: `
            <input type="number" id="amount" class="swal2-input" placeholder="Enter amount" min="100" step="100">
            <select id="paymentMethod" class="swal2-input">
                <option value="bank_transfer">Bank Transfer</option>
                <option value="card">Credit/Debit Card</option>
                <option value="wallet">Wallet Balance</option>
            </select>
            <div style="margin-top: 10px; text-align: left; font-size: 13px; color: #666;">
                <p><i class="fas fa-info-circle" style="color: #ff5e00;"></i> Minimum deposit: ₦100</p>
                <p><i class="fas fa-clock" style="color: #ff5e00;"></i> Funds are added instantly</p>
            </div>
        `,
        confirmButtonText: 'Add Funds',
        confirmButtonColor: '#ff5e00',
        showCancelButton: true,
        preConfirm: () => {
            const amount = document.getElementById('amount').value;
            if (!amount || amount < 100) {
                Swal.showValidationMessage('Please enter a valid amount (minimum ₦100)');
                return false;
            }
            return { amount: parseFloat(amount) };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Funds Added!',
                html: `
                    <p>₦${result.value.amount.toLocaleString()} has been added to your wallet</p>
                    <p style="font-size: 13px; color: #666; margin-top: 10px;">Transaction ID: TXN-${Math.random().toString(36).substr(2, 9).toUpperCase()}</p>
                `,
                timer: 3000,
                showConfirmButton: true,
                confirmButtonColor: '#ff5e00'
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Withdraw funds
function withdrawFunds(balance) {
    if (balance < 1000) {
        Swal.fire({
            icon: 'warning',
            title: 'Insufficient Balance',
            text: 'Minimum withdrawal amount is ₦1,000',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    Swal.fire({
        title: 'Withdraw Funds',
        html: `
            <p style="margin-bottom: 15px;">Available balance: <strong>₦${balance.toLocaleString()}</strong></p>
            <input type="number" id="withdraw-amount" class="swal2-input" placeholder="Enter amount" min="1000" max="${balance}" step="100">
            <select id="bank-name" class="swal2-input">
                <option value="">Select Bank</option>
                <option value="Access Bank">Access Bank</option>
                <option value="GTBank">GTBank</option>
                <option value="First Bank">First Bank</option>
                <option value="UBA">UBA</option>
                <option value="Zenith">Zenith Bank</option>
                <option value="Fidelity">Fidelity Bank</option>
                <option value="Union Bank">Union Bank</option>
            </select>
            <input type="text" id="account-number" class="swal2-input" placeholder="Account Number" maxlength="10">
            <input type="text" id="account-name" class="swal2-input" placeholder="Account Name">
            <div style="margin-top: 10px; text-align: left; font-size: 13px; color: #666;">
                <p><i class="fas fa-clock" style="color: #ff5e00;"></i> Withdrawals are processed within 24-48 hours</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Withdraw',
        confirmButtonColor: '#ff5e00',
        preConfirm: () => {
            const amount = parseFloat(document.getElementById('withdraw-amount').value);
            const bank = document.getElementById('bank-name').value;
            const account = document.getElementById('account-number').value;
            const name = document.getElementById('account-name').value;
            
            if (!amount || amount < 1000) {
                Swal.showValidationMessage('Minimum withdrawal is ₦1,000');
                return false;
            }
            if (amount > balance) {
                Swal.showValidationMessage('Insufficient balance');
                return false;
            }
            if (!bank) {
                Swal.showValidationMessage('Please select a bank');
                return false;
            }
            if (!account || account.length !== 10 || !/^\d+$/.test(account)) {
                Swal.showValidationMessage('Please enter a valid 10-digit account number');
                return false;
            }
            if (!name) {
                Swal.showValidationMessage('Please enter account name');
                return false;
            }
            return { amount, bank, account, name };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Withdrawal Request Submitted',
                html: `
                    <p>Amount: <strong>₦${result.value.amount.toLocaleString()}</strong></p>
                    <p>Bank: ${result.value.bank}</p>
                    <p>Account: ${result.value.account} (${result.value.name})</p>
                    <p style="margin-top: 15px; font-size: 13px; color: #666;">Your withdrawal will be processed within 24-48 hours.</p>
                `,
                confirmButtonColor: '#ff5e00'
            });
        }
    });
}

// Add payment method
function addPaymentMethod() {
    Swal.fire({
        title: 'Add Payment Method',
        html: `
            <select id="payment-type" class="swal2-input">
                <option value="card">Credit/Debit Card</option>
                <option value="bank_transfer">Bank Transfer</option>
            </select>
            <input type="text" id="bank-name" class="swal2-input" placeholder="Bank Name">
            <input type="text" id="account-name" class="swal2-input" placeholder="Account Name">
            <input type="text" id="account-number" class="swal2-input" placeholder="Account Number" maxlength="10">
            <label class="flex items-center gap-2 mt-2" style="justify-content: center;">
                <input type="checkbox" id="set-default"> 
                <span class="text-sm">Set as default payment method</span>
            </label>
        `,
        showCancelButton: true,
        confirmButtonText: 'Add Method',
        confirmButtonColor: '#ff5e00',
        preConfirm: () => {
            const type = document.getElementById('payment-type').value;
            const bank = document.getElementById('bank-name').value;
            const name = document.getElementById('account-name').value;
            const number = document.getElementById('account-number').value;
            const isDefault = document.getElementById('set-default').checked;
            
            if (!bank || !name || !number) {
                Swal.showValidationMessage('Please fill all fields');
                return false;
            }
            if (number.length !== 10 || !/^\d+$/.test(number)) {
                Swal.showValidationMessage('Please enter a valid 10-digit account number');
                return false;
            }
            return { type, bank, name, number, isDefault };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Method Added',
                text: 'Payment method added successfully',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Show payment options
function showPaymentOptions(methodId, methodType) {
    Swal.fire({
        title: 'Payment Method Options',
        html: `
            <div style="text-align: left;">
                <button onclick="setDefaultPayment('${methodId}')" style="width: 100%; padding: 12px; margin-bottom: 10px; background: #f5f5f5; border: none; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-check-circle" style="color: #ff5e00; margin-right: 10px;"></i> Set as Default
                </button>
                <button onclick="removePaymentMethod('${methodId}')" style="width: 100%; padding: 12px; background: #fee2e2; border: none; border-radius: 8px; color: #dc2626; cursor: pointer;">
                    <i class="fas fa-trash" style="margin-right: 10px;"></i> Remove Method
                </button>
            </div>
        `,
        showConfirmButton: false,
        showCloseButton: true
    });
}

// Set default payment method
function setDefaultPayment(methodId) {
    Swal.fire({
        icon: 'success',
        title: 'Default Set',
        text: 'Payment method set as default',
        timer: 1500,
        showConfirmButton: false
    }).then(() => {
        location.reload();
    });
}

// Remove payment method
function removePaymentMethod(methodId) {
    Swal.fire({
        title: 'Remove Payment Method?',
        text: 'Are you sure you want to remove this payment method?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Remove',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Removed',
                text: 'Payment method removed successfully',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Check notifications
function checkNotifications() {
    Swal.fire({
        icon: 'info',
        title: 'Notifications',
        html: `
            <div style="text-align: left;">
                <p>🔔 No new notifications</p>
            </div>
        `,
        confirmButtonColor: '#ff5e00'
    });
}

// Show coming soon message
function showComingSoon(feature) {
    Swal.fire({
        icon: 'info',
        title: 'Coming Soon!',
        text: `${feature} feature will be available in the next update.`,
        confirmButtonColor: '#ff5e00'
    });
}