<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

// Handle Add Delivery AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_delivery') {
    $customer_id = intval($_POST['customer_id']);
    $goods_type = mysqli_real_escape_string($conn, $_POST['goods_type']);
    $weight_tonnes = floatval($_POST['weight_tonnes']);
    $delivery_date = mysqli_real_escape_string($conn, $_POST['delivery_date']);
    $time_window_start = mysqli_real_escape_string($conn, $_POST['time_window_start']);
    $time_window_end = mysqli_real_escape_string($conn, $_POST['time_window_end']);
    
    $code_query = mysqli_query($conn, "SELECT MAX(id) as last_id FROM deliveries");
    $last = mysqli_fetch_assoc($code_query);
    $new_id = $last['last_id'] + 1;
    $delivery_code = 'DEL-' . str_pad($new_id, 3, '0', STR_PAD_LEFT);
    
    $query = "INSERT INTO deliveries (delivery_code, customer_id, goods_type, weight_tonnes, delivery_date, time_window_start, time_window_end, status) 
              VALUES ('$delivery_code', $customer_id, '$goods_type', $weight_tonnes, '$delivery_date', '$time_window_start', '$time_window_end', 'pending')";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'Delivery added successfully', 'delivery_code' => $delivery_code]);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
    exit();
}

// Handle AJAX customer search
if (isset($_GET['action']) && $_GET['action'] === 'search_customers') {
    $search = mysqli_real_escape_string($conn, $_GET['term']);
    $query = "SELECT id, name FROM customers WHERE name LIKE '%$search%' ORDER BY name LIMIT 10";
    $result = mysqli_query($conn, $query);
    $customers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = ['id' => $row['id'], 'name' => $row['name']];
    }
    echo json_encode($customers);
    exit();
}

$deliveries = mysqli_query($conn, "SELECT d.id, d.delivery_code, d.weight_tonnes, d.delivery_date, d.time_window_start, d.time_window_end, d.status, c.name as customer_name 
    FROM deliveries d 
    LEFT JOIN customers c ON d.customer_id = c.id 
    ORDER BY d.id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Deliveries - Unga Logistics</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; }
        .sidebar {
            background: #1a202c;
            width: 250px;
            position: fixed;
            height: 100%;
            padding: 2rem 1rem;
        }
        .sidebar h2 { color: #d4af37; margin-bottom: 2rem; }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px;
            margin: 5px 0;
            border-radius: 6px;
        }
        .sidebar a:hover, .sidebar a.active { background: #4a5568; }
        .content { margin-left: 250px; padding: 2rem; }
        .header {
            background: white;
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-add {
            background: #48bb78;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-add:hover { background: #38a169; }
        .table-container {
            background: white;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }
        th, td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
            vertical-align: top;
        }
        th {
            background: #f7fafc;
            font-weight: 600;
            white-space: nowrap;
        }
        .btn {
            padding: 4px 8px;
            background: #4299e1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 11px;
            display: inline-block;
        }
        .status-pending { background: #ed8936; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; display: inline-block; }
        .status-assigned { background: #4299e1; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; display: inline-block; }
        .status-in_transit { background: #9f7aea; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; display: inline-block; }
        .status-delivered { background: #48bb78; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; display: inline-block; }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            width: 500px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .modal-content h3 { margin-bottom: 1.5rem; color: #2d3748; }
        .modal-content input, .modal-content select, .modal-content textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .modal-content label { font-weight: bold; display: block; margin-bottom: 5px; font-size: 13px; color: #4a5568; }
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        .modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-submit { background: #48bb78; color: white; }
        .btn-submit:hover { background: #38a169; }
        .btn-cancel { background: #a0aec0; color: white; }
        .btn-cancel:hover { background: #718096; }
        .close { float: right; font-size: 24px; cursor: pointer; color: #a0aec0; }
        .close:hover { color: #2d3748; }
        
        .autocomplete-container { position: relative; }
        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1001;
            display: none;
        }
        .autocomplete-results div {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .autocomplete-results div:hover { background: #f0f2f5; }
        .selected-customer {
            background: #e8f5e9;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: none;
            font-size: 14px;
        }
        .selected-customer span { font-weight: bold; }
        
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #48bb78;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            display: none;
            z-index: 1001;
        }
        .toast.error { background: #e53e3e; }
        
        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 1rem; }
            .sidebar { display: none; }
            th, td { font-size: 11px; padding: 8px 4px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Unga Logistics</h2>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="vehicles.php">Vehicles</a>
        <a href="deliveries.php" class="active">Deliveries</a>
        <a href="drivers.php">Drivers</a>
        <a href="ga_optimize.php">GA Optimization</a>
        <a href="admin_notifications.php">Notifications</a>
        <a href="reports.php">Reports</a>
        <a href="admin_issues.php">Issues</a>
        <a href="logout.php">Logout</a>
    </div>
    
    <div class="content">
        <div class="header">
            <h2>All Deliveries</h2>
            <button class="btn-add" onclick="openModal()">+ Add New Delivery</button>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Delivery Code</th>
                        <th>Customer</th>
                        <th>Weight (t)</th>
                        <th>Delivery Date</th>
                        <th>Time Window</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($deliveries)): ?>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 10px 8px;"><?php echo $row['id']; ?></td>
                        <td style="padding: 10px 8px;"><?php echo $row['delivery_code']; ?></td>
                        <td style="padding: 10px 8px; max-width: 200px; word-break: break-word; white-space: normal;"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                        <td style="padding: 10px 8px;"><?php echo $row['weight_tonnes']; ?> t</td>
                        <td style="padding: 10px 8px;"><?php echo $row['delivery_date'] ?: '—'; ?></td>
                        <td style="padding: 10px 8px;">
                            <?php 
                            if($row['time_window_start'] && $row['time_window_end']) {
                                echo date('H:i', strtotime($row['time_window_start'])) . ' - ' . date('H:i', strtotime($row['time_window_end']));
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td style="padding: 10px 8px;">
                            <span class="status-<?php echo str_replace('_', '-', $row['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                            </span>
                        </td>
                        <td style="padding: 10px 8px;">
                            <a href="view.php?id=<?php echo $row['id']; ?>" class="btn">View</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Delivery Modal -->
    <div id="deliveryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Add New Delivery</h3>
            <form id="addDeliveryForm">
                <label>Customer *</label>
                <div class="autocomplete-container">
                    <input type="text" id="customerSearch" placeholder="Type customer name..." autocomplete="off">
                    <input type="hidden" id="customer_id" name="customer_id" required>
                    <div id="autocompleteResults" class="autocomplete-results"></div>
                </div>
                <div id="selectedCustomer" class="selected-customer"></div>
                
                <label>Goods Type</label>
                <input type="text" name="goods_type" value="Maize Flour" placeholder="Goods Type">
                
                <label>Weight (tonnes) *</label>
                <input type="number" name="weight_tonnes" step="0.01" required placeholder="Weight in tonnes">
                
                <label>Delivery Date *</label>
                <input type="date" name="delivery_date" required>
                
                <label>Time Window Start *</label>
                <input type="time" name="time_window_start" value="08:00" required>
                
                <label>Time Window End *</label>
                <input type="time" name="time_window_end" value="17:00" required>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Add Delivery</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="toast" class="toast"></div>
    
    <script>
        const modal = document.getElementById('deliveryModal');
        const toast = document.getElementById('toast');
        let searchTimeout;
        
        function openModal() {
            modal.style.display = 'block';
            document.getElementById('addDeliveryForm').reset();
            document.getElementById('customerSearch').value = '';
            document.getElementById('customer_id').value = '';
            document.getElementById('selectedCustomer').style.display = 'none';
            document.getElementById('autocompleteResults').style.display = 'none';
            document.querySelector('input[name="delivery_date"]').valueAsDate = new Date();
            document.querySelector('input[name="time_window_start"]').value = '08:00';
            document.querySelector('input[name="time_window_end"]').value = '17:00';
        }
        
        function closeModal() {
            modal.style.display = 'none';
        }
        
        function showToast(message, isError = false) {
            toast.textContent = message;
            toast.className = isError ? 'toast error' : 'toast';
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
        
        const searchInput = document.getElementById('customerSearch');
        const resultsDiv = document.getElementById('autocompleteResults');
        const customerIdInput = document.getElementById('customer_id');
        const selectedDiv = document.getElementById('selectedCustomer');
        
        searchInput.addEventListener('input', function() {
            const term = this.value.trim();
            
            if (term.length < 2) {
                resultsDiv.style.display = 'none';
                return;
            }
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetch(`?action=search_customers&term=${encodeURIComponent(term)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            resultsDiv.innerHTML = data.map(c => `<div data-id="${c.id}" data-name="${c.name}">${c.name}</div>`).join('');
                            resultsDiv.style.display = 'block';
                        } else {
                            resultsDiv.innerHTML = '<div style="color:#999;">No customers found</div>';
                            resultsDiv.style.display = 'block';
                        }
                    });
            }, 300);
        });
        
        resultsDiv.addEventListener('click', function(e) {
            const div = e.target.closest('div');
            if (div && div.dataset.id) {
                const id = div.dataset.id;
                const name = div.dataset.name;
                searchInput.value = name;
                customerIdInput.value = id;
                selectedDiv.innerHTML = `Selected: <span>${name}</span>`;
                selectedDiv.style.display = 'block';
                resultsDiv.style.display = 'none';
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.style.display = 'none';
            }
        });
        
        document.getElementById('addDeliveryForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!customerIdInput.value) {
                showToast('Please select a customer from the search results', true);
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'add_delivery');
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    showToast('Delivery ' + result.delivery_code + ' added successfully!');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('Error: ' + result.message, true);
                }
            } catch (error) {
                showToast('Error adding delivery', true);
            }
        });
        
        window.onclick = function(event) {
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>