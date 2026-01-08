<?php
require_once dirname(__FILE__) . '/includes/auth.php';
requireLogin();

$config = json_decode(file_get_contents(dirname(__FILE__) . '/../config/backup-config.json'), true);
$plans = $config['plans'] ?? [];
$payment_methods = $config['payment_methods'] ?? [];
$yearly_discount = $config['yearly_discount_percent'] ?? 17;

$page_title = 'Backup Storage Plans';
include 'includes/header.php';
?>

<div class="content-wrapper">
    <h1>FlexPBX Cloud Backup Storage</h1>
    <p class="subtitle">Secure remote backup storage for your PBX system</p>

    <div class="billing-toggle">
        <label class="toggle-label">
            <input type="radio" name="billing" value="monthly" checked onclick="toggleBilling('monthly')"> Monthly
        </label>
        <label class="toggle-label">
            <input type="radio" name="billing" value="yearly" onclick="toggleBilling('yearly')"> 
            Yearly <span class="discount-badge">Save <?php echo $yearly_discount; ?>%</span>
        </label>
    </div>

    <div class="plans-grid">
        <?php foreach ($plans as $plan): ?>
        <div class="plan-card <?php echo ($plan['recommended'] ?? false) ? 'recommended' : ''; ?>">
            <?php if ($plan['recommended'] ?? false): ?>
                <div class="recommended-badge">Most Popular</div>
            <?php endif; ?>
            
            <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
            <div class="plan-tier"><?php echo htmlspecialchars($config['storage_tiers'][$plan['tier']]['name'] ?? ''); ?></div>
            
            <div class="price monthly-price">
                <?php if ($plan['price_monthly_usd'] == 0): ?>
                    <span class="amount">Free</span>
                <?php else: ?>
                    <span class="currency">$</span>
                    <span class="amount"><?php echo $plan['price_monthly_usd']; ?></span>
                    <span class="period">/month</span>
                <?php endif; ?>
            </div>
            
            <div class="price yearly-price" style="display:none;">
                <?php if ($plan['price_yearly_usd'] == 0): ?>
                    <span class="amount">Free</span>
                <?php else: ?>
                    <span class="currency">$</span>
                    <span class="amount"><?php echo $plan['price_yearly_usd']; ?></span>
                    <span class="period">/year</span>
                <?php endif; ?>
            </div>

            <div class="ecripto-price">
                <img src="../images/ecripto-icon.svg" alt="eCripto" class="ecripto-icon">
                <span class="monthly-ecr"><?php echo $plan['price_monthly_ecripto']; ?> ECR/mo</span>
                <span class="yearly-ecr" style="display:none;"><?php echo $plan['price_yearly_ecripto']; ?> ECR/yr</span>
                <span class="ecripto-discount">15% off</span>
            </div>

            <ul class="plan-specs">
                <li><strong><?php echo $plan['storage_gb']; ?> GB</strong> Storage</li>
                <li><strong><?php echo $plan['max_backups'] == -1 ? 'Unlimited' : $plan['max_backups']; ?></strong> Backups</li>
                <li><strong><?php echo $plan['retention_days'] == -1 ? 'Forever' : $plan['retention_days'] . ' days'; ?></strong> Retention</li>
            </ul>

            <ul class="plan-features">
                <?php foreach ($plan['features'] ?? [] as $feature): ?>
                    <li><span class="check">✓</span> <?php echo htmlspecialchars($feature); ?></li>
                <?php endforeach; ?>
            </ul>

            <?php if (isset($plan['limitations'])): ?>
            <ul class="plan-limitations">
                <?php foreach ($plan['limitations'] as $limit): ?>
                    <li><span class="x">✗</span> <?php echo htmlspecialchars($limit); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <button class="btn btn-primary btn-select-plan" data-plan="<?php echo $plan['id']; ?>">
                <?php echo $plan['price_monthly_usd'] == 0 ? 'Start Free' : 'Select Plan'; ?>
            </button>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="payment-modal" class="modal">
    <div class="modal-content">
        <h2>Complete Your Subscription</h2>
        <div id="selected-plan-summary"></div>
        
        <h3>Select Payment Method</h3>
        <div class="payment-methods">
            <?php foreach ($payment_methods as $method): ?>
            <?php if ($method['enabled']): ?>
            <div class="payment-option" data-method="<?php echo $method['id']; ?>">
                <div class="payment-icon">
                    <?php if ($method['id'] === 'ecripto' || $method['id'] === 'ecripto_app'): ?>
                        <img src="../images/ecripto-icon.svg" alt="eCripto">
                    <?php endif; ?>
                </div>
                <div class="payment-info">
                    <strong><?php echo $method['name']; ?></strong>
                    <?php if (isset($method['discount_percent'])): ?>
                        <span class="payment-discount"><?php echo $method['discount_percent']; ?>% OFF</span>
                    <?php endif; ?>
                    <br><small><?php echo $method['description']; ?></small>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div id="ecripto-wallet-section" style="display:none;">
            <h4>Connect eCripto Wallet</h4>
            <button id="connect-wallet-btn" class="btn">Connect Wallet</button>
            <div id="wallet-info" style="display:none;">
                <p>Connected: <span id="wallet-address"></span></p>
                <p>Balance: <span id="wallet-balance"></span></p>
            </div>
        </div>

        <div class="modal-actions">
            <button class="btn" onclick="closePaymentModal()">Cancel</button>
            <button class="btn btn-primary" id="proceed-payment-btn">Proceed to Payment</button>
        </div>
    </div>
</div>

<script>
var selectedPlan = null;
var selectedMethod = null;
var billingCycle = 'monthly';

function toggleBilling(cycle) {
    billingCycle = cycle;
    document.querySelectorAll('.monthly-price, .monthly-ecr').forEach(el => {
        el.style.display = cycle === 'monthly' ? 'block' : 'none';
    });
    document.querySelectorAll('.yearly-price, .yearly-ecr').forEach(el => {
        el.style.display = cycle === 'yearly' ? 'block' : 'none';
    });
}

document.querySelectorAll('.btn-select-plan').forEach(btn => {
    btn.addEventListener('click', function() {
        selectedPlan = this.dataset.plan;
        document.getElementById('payment-modal').style.display = 'flex';
    });
});

document.querySelectorAll('.payment-option').forEach(opt => {
    opt.addEventListener('click', function() {
        document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
        selectedMethod = this.dataset.method;
        
        if (selectedMethod === 'ecripto' || selectedMethod === 'ecripto_app') {
            document.getElementById('ecripto-wallet-section').style.display = 'block';
        } else {
            document.getElementById('ecripto-wallet-section').style.display = 'none';
        }
    });
});

document.getElementById('proceed-payment-btn').addEventListener('click', function() {
    if (!selectedMethod) {
        alert('Please select a payment method');
        return;
    }
    window.location.href = 'backup-payment.php?plan=' + selectedPlan + '&method=' + selectedMethod + '&cycle=' + billingCycle;
});

function closePaymentModal() {
    document.getElementById('payment-modal').style.display = 'none';
}
</script>

<?php include 'includes/footer.php'; ?>
