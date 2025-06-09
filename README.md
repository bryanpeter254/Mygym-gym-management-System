# Gym Management System - XAMPP Version

This is the XAMPP-compatible version of the Gym Management System, which allows for easier deployment on Windows systems using XAMPP.

## Features

- **Member Registration**: Register new gym members with all necessary information
- **Fingerprint Integration**: Capture and verify fingerprints using ZKT4500 fingerprint scanner
- **Check-in System**: Record member attendance with both fingerprint and manual check-in options
- **Membership Management**: Different membership types with automatic renewal date calculation
- **Reporting**: Generate membership, attendance, and revenue reports
- **Export Functionality**: Export reports in CSV and PDF formats
- **Responsive Design**: Works on desktop, tablet, and mobile devices

## Requirements

- XAMPP (with PHP 7.4+ and MySQL)
- Web Browser (Chrome, Firefox, Edge, etc.)
- ZKT4500 Fingerprint Scanner (for fingerprint functionality)
- Java Runtime Environment (for fingerprint bridge service)

## Installation

1. **Install XAMPP**:
   - Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
   - Start Apache and MySQL services from the XAMPP Control Panel

2. **Deploy the Application**:
   - Copy the entire `xampp-version` folder to `xampp/htdocs/` directory
   - Rename the folder to `gym-manager` or your preferred name

3. **Database Setup**:
   - Open your web browser and navigate to `http://localhost/gym-manager/setup.php`
   - This will create the database, tables, and insert default data
   - After successful setup, you'll be redirected to the homepage

4. **Fingerprint Scanner Setup** (optional):
   - Ensure the ZKT4500 fingerprint scanner is connected to your computer
   - Install the required drivers for the scanner
   - Start the Java bridge service by running the JAR file:
     ```
     java -jar fingerprint-bridge.jar
     ```
   - The bridge service will run on port 8099 by default

## Usage

1. **Accessing the System**:
   - Open your web browser and navigate to `http://localhost/gym-manager/`
   - The system doesn't require login by default, but you can implement it if needed

2. **Registering Members**:
   - Click on "New Member" button in the navigation bar
   - Fill in the member details
   - Optionally scan their fingerprint
   - Submit the form to register the member

3. **Check-in Process**:
   - Go to the "Check-in" page
   - For fingerprint check-in, have the member place their finger on the scanner and click "Scan Fingerprint"
   - For manual check-in, search for the member and click "Check-in"

4. **Generating Reports**:
   - Go to the "Reports" page
   - Select the report type (Membership, Attendance, or Revenue)
   - Choose the time frame
   - Click "Generate Report"
   - Use the export buttons to download the report in CSV or PDF format

## Customization

1. **Membership Types**:
   - Modify the membership types in the `config.php` file or through the "Membership Types" page

2. **System Settings**:
   - Update system settings through the "Settings" page in the admin menu

3. **Styling**:
   - Customize the appearance by modifying the `css/styles.css` file

## Troubleshooting

1. **Database Connection Issues**:
   - Ensure MySQL is running in XAMPP Control Panel
   - Check the database credentials in `config.php`

2. **Fingerprint Scanner Issues**:
   - Verify the scanner is properly connected
   - Check if the Java bridge service is running
   - Ensure the correct port is configured in `config.php`

3. **Export Functionality Issues**:
   - For PDF exports, ensure the TCPDF library is correctly installed in the `lib/tcpdf/` directory

## Credits

- Developed by: Bryan Peter
- QR code scanner: Google Scanner Api
- Frontend: Bootstrap 5, Font Awesome
- Backend: PHP, MySQL

## License

This software is provided as-is without any warranty. You are free to use, modify, and distribute it as needed.