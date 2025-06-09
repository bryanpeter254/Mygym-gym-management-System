<?php
// Include database configuration
require_once 'config.php';

// Verify format parameter
$format = isset($_GET['format']) ? sanitize($_GET['format']) : 'csv';
if (!in_array($format, ['csv', 'pdf'])) {
    die("Invalid export format");
}

// Get export parameters
$report_type = isset($_GET['report']) ? sanitize($_GET['report']) : '';
$time_frame = isset($_GET['time_frame']) ? sanitize($_GET['time_frame']) : 'all';
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';

// If no report type is specified, check if this is a member export
$is_members_export = empty($report_type) && isset($_GET['status']);
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$membership_filter = isset($_GET['membership']) ? (int)$_GET['membership'] : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query based on export type
if ($is_members_export) {
    // Members export from members.php
    $query = "SELECT m.id, m.first_name, m.last_name, m.email, m.phone, m.gender, 
                     m.dob, m.address, m.status, m.registration_date, m.start_date, 
                     m.renewal_date, m.special_comments, m.medical_conditions,
                     mt.name as membership_type 
              FROM members m
              LEFT JOIN membership_types mt ON m.membership_type_id = mt.id
              WHERE 1=1";
    
    // Apply status filter
    if ($status_filter !== 'all') {
        $query .= " AND m.status = '$status_filter'";
    }
    
    // Apply membership filter
    if ($membership_filter > 0) {
        $query .= " AND m.membership_type_id = $membership_filter";
    }
    
    // Apply search filter
    if (!empty($search)) {
        $query .= " AND (m.first_name LIKE '%$search%' OR m.last_name LIKE '%$search%' OR 
                          m.email LIKE '%$search%' OR m.phone LIKE '%$search%')";
    }
    
    // Order by
    $query .= " ORDER BY m.last_name, m.first_name";
    
    // Set filename
    $filename = 'members_export_' . date('Y-m-d');
    
} else {
    // Report export
    if ($report_type === 'membership') {
        $query = "SELECT m.id, m.first_name, m.last_name, m.email, m.phone, m.gender, 
                         m.dob, m.status, m.registration_date, m.renewal_date,
                         mt.name as membership_type 
                  FROM members m
                  LEFT JOIN membership_types mt ON m.membership_type_id = mt.id
                  WHERE 1=1";
        
        // Apply time frame filter if needed
        if ($time_frame !== 'all') {
            $date_condition = '';
            
            switch ($time_frame) {
                case 'today':
                    $date_condition = "DATE(m.registration_date) = CURDATE()";
                    break;
                case 'yesterday':
                    $date_condition = "DATE(m.registration_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                    break;
                case 'this_week':
                    $date_condition = "YEARWEEK(m.registration_date, 1) = YEARWEEK(CURDATE(), 1)";
                    break;
                case 'last_week':
                    $date_condition = "YEARWEEK(m.registration_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
                    break;
                case 'this_month':
                    $date_condition = "YEAR(m.registration_date) = YEAR(CURDATE()) AND MONTH(m.registration_date) = MONTH(CURDATE())";
                    break;
                case 'last_month':
                    $date_condition = "YEAR(m.registration_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(m.registration_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                    break;
                case 'custom':
                    if (!empty($start_date) && !empty($end_date)) {
                        $date_condition = "DATE(m.registration_date) BETWEEN '$start_date' AND '$end_date'";
                    }
                    break;
            }
            
            if (!empty($date_condition)) {
                $query .= " AND $date_condition";
            }
        }
        
        $query .= " ORDER BY m.last_name, m.first_name";
        $filename = 'membership_report_' . date('Y-m-d');
        
    } elseif ($report_type === 'attendance') {
        $query = "SELECT c.id, c.check_in_time, c.check_out_time, c.verification_method,
                         m.first_name, m.last_name, m.email, m.phone,
                         mt.name as membership_type
                  FROM check_ins c
                  JOIN members m ON c.member_id = m.id
                  LEFT JOIN membership_types mt ON m.membership_type_id = mt.id
                  WHERE 1=1";
        
        // Apply time frame filter if needed
        if ($time_frame !== 'all') {
            $date_condition = '';
            
            switch ($time_frame) {
                case 'today':
                    $date_condition = "DATE(c.check_in_time) = CURDATE()";
                    break;
                case 'yesterday':
                    $date_condition = "DATE(c.check_in_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                    break;
                case 'this_week':
                    $date_condition = "YEARWEEK(c.check_in_time, 1) = YEARWEEK(CURDATE(), 1)";
                    break;
                case 'last_week':
                    $date_condition = "YEARWEEK(c.check_in_time, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
                    break;
                case 'this_month':
                    $date_condition = "YEAR(c.check_in_time) = YEAR(CURDATE()) AND MONTH(c.check_in_time) = MONTH(CURDATE())";
                    break;
                case 'last_month':
                    $date_condition = "YEAR(c.check_in_time) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(c.check_in_time) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                    break;
                case 'custom':
                    if (!empty($start_date) && !empty($end_date)) {
                        $date_condition = "DATE(c.check_in_time) BETWEEN '$start_date' AND '$end_date'";
                    }
                    break;
            }
            
            if (!empty($date_condition)) {
                $query .= " AND $date_condition";
            }
        }
        
        $query .= " ORDER BY c.check_in_time DESC";
        $filename = 'attendance_report_' . date('Y-m-d');
        
    } elseif ($report_type === 'revenue') {
        // For revenue, we'll create a summary first
        $summary_query = "SELECT mt.name, COUNT(m.id) as member_count, mt.price, (COUNT(m.id) * mt.price) as revenue
                          FROM membership_types mt
                          LEFT JOIN members m ON mt.id = m.membership_type_id AND m.status = 'Active'
                          GROUP BY mt.id
                          ORDER BY revenue DESC";
        
        $query = "SELECT m.id, m.first_name, m.last_name, m.status, m.registration_date, 
                         m.payment_date, m.start_date, m.renewal_date,
                         mt.name as membership_type, mt.price
                  FROM members m
                  LEFT JOIN membership_types mt ON m.membership_type_id = mt.id
                  WHERE m.status = 'Active'";
        
        // Apply time frame filter if needed
        if ($time_frame !== 'all') {
            $date_condition = '';
            
            switch ($time_frame) {
                case 'today':
                    $date_condition = "DATE(m.payment_date) = CURDATE()";
                    break;
                case 'yesterday':
                    $date_condition = "DATE(m.payment_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                    break;
                case 'this_week':
                    $date_condition = "YEARWEEK(m.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
                    break;
                case 'last_week':
                    $date_condition = "YEARWEEK(m.payment_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
                    break;
                case 'this_month':
                    $date_condition = "YEAR(m.payment_date) = YEAR(CURDATE()) AND MONTH(m.payment_date) = MONTH(CURDATE())";
                    break;
                case 'last_month':
                    $date_condition = "YEAR(m.payment_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(m.payment_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                    break;
                case 'custom':
                    if (!empty($start_date) && !empty($end_date)) {
                        $date_condition = "DATE(m.payment_date) BETWEEN '$start_date' AND '$end_date'";
                    }
                    break;
            }
            
            if (!empty($date_condition)) {
                $query .= " AND $date_condition";
            }
        }
        
        $query .= " ORDER BY mt.price DESC, m.last_name, m.first_name";
        $filename = 'revenue_report_' . date('Y-m-d');
        
    } else {
        // Default to members if report type is not recognized
        $query = "SELECT m.id, m.first_name, m.last_name, m.email, m.phone, m.gender,
                         m.status, m.registration_date, m.renewal_date,
                         mt.name as membership_type 
                  FROM members m
                  LEFT JOIN membership_types mt ON m.membership_type_id = mt.id
                  ORDER BY m.last_name, m.first_name";
        $filename = 'members_export_' . date('Y-m-d');
    }
}

// Execute the query
$result = mysqli_query($conn, $query);

// Check for error
if (!$result) {
    die("Error executing query: " . mysqli_error($conn));
}

// Generate the export based on format
if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Get the column names from result
    $columns = [];
    $num_fields = mysqli_num_fields($result);
    
    for ($i = 0; $i < $num_fields; $i++) {
        $field_info = mysqli_fetch_field_direct($result, $i);
        $columns[] = $field_info->name;
    }
    
    // Write header row
    fputcsv($output, $columns);
    
    // Write data rows
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
    
    // For revenue report, add summary data
    if ($report_type === 'revenue') {
        // Add a blank row as separator
        fputcsv($output, array_fill(0, count($columns), ''));
        
        // Add Revenue Summary header
        fputcsv($output, ['Revenue Summary']);
        fputcsv($output, ['Membership Type', 'Number of Members', 'Price (KES)', 'Revenue (KES)']);
        
        // Add summary data
        $summary_result = mysqli_query($conn, $summary_query);
        if ($summary_result) {
            $total_members = 0;
            $total_revenue = 0;
            
            while ($summary_row = mysqli_fetch_assoc($summary_result)) {
                fputcsv($output, [
                    $summary_row['name'],
                    $summary_row['member_count'],
                    $summary_row['price'],
                    $summary_row['revenue']
                ]);
                
                $total_members += $summary_row['member_count'];
                $total_revenue += $summary_row['revenue'];
            }
            
            // Add totals row
            fputcsv($output, ['Total', $total_members, '', $total_revenue]);
        }
    }
    
    fclose($output);
    
} else {
    // PDF Export
    // Include the TCPDF library (assumed to be installed)
    require_once('lib/tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Gym Management System');
    $pdf->SetAuthor('Gym Admin');
    $pdf->SetTitle(ucfirst($report_type) . ' Report');
    $pdf->SetSubject(ucfirst($report_type) . ' Report');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'Gym Management System', ucfirst($report_type) . ' Report - ' . date('d/m/Y'));
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array('helvetica', '', 10));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont('courier');
    
    // Set margins
    $pdf->SetMargins(10, 20, 10);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Get time frame label
    $time_frame_label = 'All Time';
    switch ($time_frame) {
        case 'today':
            $time_frame_label = 'Today (' . date('d-m-Y') . ')';
            break;
        case 'yesterday':
            $time_frame_label = 'Yesterday (' . date('d-m-Y', strtotime('-1 day')) . ')';
            break;
        case 'this_week':
            $time_frame_label = 'This Week (' . date('d-m-Y', strtotime('monday this week')) . ' to ' . date('d-m-Y', strtotime('sunday this week')) . ')';
            break;
        case 'last_week':
            $time_frame_label = 'Last Week (' . date('d-m-Y', strtotime('monday last week')) . ' to ' . date('d-m-Y', strtotime('sunday last week')) . ')';
            break;
        case 'this_month':
            $time_frame_label = 'This Month (' . date('F Y') . ')';
            break;
        case 'last_month':
            $time_frame_label = 'Last Month (' . date('F Y', strtotime('first day of last month')) . ')';
            break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $time_frame_label = 'Custom Period (' . date('d-m-Y', strtotime($start_date)) . ' to ' . date('d-m-Y', strtotime($end_date)) . ')';
            } else {
                $time_frame_label = 'Custom Period';
            }
            break;
    }
    
    // Add report title and time frame
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, ucfirst($report_type) . ' Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, $time_frame_label, 0, 1, 'C');
    $pdf->Ln(5);
    
    // Reset to the beginning of result set
    mysqli_data_seek($result, 0);
    
    // Get the column names from result
    $columns = [];
    $num_fields = mysqli_num_fields($result);
    
    for ($i = 0; $i < $num_fields; $i++) {
        $field_info = mysqli_fetch_field_direct($result, $i);
        // Format column name (replace underscores with spaces, capitalize words)
        $column_name = ucwords(str_replace('_', ' ', $field_info->name));
        $columns[] = $column_name;
    }
    
    // Determine column widths based on number of columns
    $total_width = 275; // A4 landscape width minus margins
    $column_width = $total_width / $num_fields;
    
    // Create the table header
    $pdf->SetFillColor(200, 220, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    
    foreach ($columns as $column) {
        $pdf->Cell($column_width, 7, $column, 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Create the table rows
    $pdf->SetFont('helvetica', '', 9);
    $fill = false;
    
    while ($row = mysqli_fetch_array($result)) {
        foreach ($row as $key => $value) {
            // Skip numeric keys (we only want associative keys)
            if (is_numeric($key)) continue;
            
            // Format dates
            if (strpos($key, 'date') !== false && !empty($value)) {
                $value = date('d-m-Y', strtotime($value));
            }
            
            // Format times
            if (strpos($key, 'time') !== false && !empty($value)) {
                $value = date('d-m-Y H:i', strtotime($value));
            }
            
            $pdf->Cell($column_width, 6, $value, 1, 0, 'L', $fill);
        }
        $pdf->Ln();
        $fill = !$fill; // Alternate row colors
    }
    
    // For revenue report, add summary data
    if ($report_type === 'revenue') {
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Revenue Summary', 0, 1, 'L');
        
        // Add summary table
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(100, 7, 'Membership Type', 1, 0, 'C', 1);
        $pdf->Cell(50, 7, 'Number of Members', 1, 0, 'C', 1);
        $pdf->Cell(50, 7, 'Price (KES)', 1, 0, 'C', 1);
        $pdf->Cell(75, 7, 'Revenue (KES)', 1, 1, 'C', 1);
        
        $pdf->SetFont('helvetica', '', 9);
        $fill = false;
        
        // Add summary data
        $summary_result = mysqli_query($conn, $summary_query);
        if ($summary_result) {
            $total_members = 0;
            $total_revenue = 0;
            
            while ($summary_row = mysqli_fetch_assoc($summary_result)) {
                $pdf->Cell(100, 6, $summary_row['name'], 1, 0, 'L', $fill);
                $pdf->Cell(50, 6, $summary_row['member_count'], 1, 0, 'C', $fill);
                $pdf->Cell(50, 6, number_format($summary_row['price']), 1, 0, 'R', $fill);
                $pdf->Cell(75, 6, number_format($summary_row['revenue']), 1, 1, 'R', $fill);
                
                $total_members += $summary_row['member_count'];
                $total_revenue += $summary_row['revenue'];
                
                $fill = !$fill;
            }
            
            // Add totals row
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(100, 7, 'Total', 1, 0, 'L', 1);
            $pdf->Cell(50, 7, $total_members, 1, 0, 'C', 1);
            $pdf->Cell(50, 7, '', 1, 0, 'R', 1);
            $pdf->Cell(75, 7, number_format($total_revenue), 1, 1, 'R', 1);
        }
    }
    
    // Output the PDF
    $pdf->Output($filename . '.pdf', 'D');
}

// Close database connection
mysqli_close($conn);