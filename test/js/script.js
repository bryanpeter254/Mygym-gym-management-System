// Main JavaScript for Gym Management System

document.addEventListener('DOMContentLoaded', function() {
    // Enable tooltips everywhere
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Enable popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Handle member search functionality
    const searchInput = document.getElementById('memberSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const value = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#membersTable tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.indexOf(value) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // QR Code related functionality for registration and check-in
    // All fingerprint-related functionality has been replaced with QR codes
    
    // Hide any stray fingerprint elements that might still be in the DOM
    document.addEventListener('DOMContentLoaded', function() {
        // Hide any elements with fingerprint in their ID or class name
        const fingerprintElements = document.querySelectorAll('[id*="fingerprint"], [class*="fingerprint"]');
        fingerprintElements.forEach(el => {
            el.style.display = 'none';
        });
    });
    
    // Handle QR Code display in registration completion
    const qrCodeContainer = document.getElementById('qrCodeContainer');
    const memberQrImage = document.getElementById('memberQrImage');
    
    // If we're on the member details page with a QR code
    if (qrCodeContainer && memberQrImage) {
        // Enable download QR Code button if present
        const downloadQrBtn = document.getElementById('downloadQrCode');
        if (downloadQrBtn) {
            downloadQrBtn.addEventListener('click', function() {
                const link = document.createElement('a');
                link.download = 'gym-qrcode.png';
                link.href = memberQrImage.src;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }
    }
    
    // Handle QR check-in functionality
    const qrCheckInBtn = document.getElementById('qrCheckInBtn');
    if (qrCheckInBtn) {
        qrCheckInBtn.addEventListener('click', function() {
            // Redirect to QR code scanner page
            window.location.href = 'qr-check-in.php';
        });
    }
    
    // Handle date fields for better date picker
    const dateFields = document.querySelectorAll('input[type="date"]');
    dateFields.forEach(field => {
        // Enable datepicker if available
        if (typeof flatpickr !== 'undefined') {
            flatpickr(field, {
                dateFormat: "Y-m-d"
            });
        }
    });
    
    // Membership renewal calculation
    const membershipSelect = document.getElementById('membershipType');
    const startDateInput = document.getElementById('startDate');
    const renewalDateInput = document.getElementById('renewalDate');
    
    if (membershipSelect && startDateInput && renewalDateInput) {
        const calculateRenewalDate = function() {
            const membershipTypeId = membershipSelect.value;
            const startDate = new Date(startDateInput.value);
            
            if (membershipTypeId && startDate && !isNaN(startDate)) {
                // Get the selected option element
                const selectedOption = membershipSelect.options[membershipSelect.selectedIndex];
                // Get duration from the data attribute
                const duration = selectedOption.getAttribute('data-duration');
                
                if (duration) {
                    // Calculate renewal date
                    const renewalDate = new Date(startDate);
                    renewalDate.setDate(renewalDate.getDate() + parseInt(duration));
                    
                    // Format date as YYYY-MM-DD
                    const year = renewalDate.getFullYear();
                    const month = String(renewalDate.getMonth() + 1).padStart(2, '0');
                    const day = String(renewalDate.getDate()).padStart(2, '0');
                    
                    // Set renewal date
                    renewalDateInput.value = `${year}-${month}-${day}`;
                }
            }
        };
        
        // Calculate on change
        membershipSelect.addEventListener('change', calculateRenewalDate);
        startDateInput.addEventListener('change', calculateRenewalDate);
    }
});