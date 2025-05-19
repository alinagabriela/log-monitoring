
Log Monitoring Application

========================

This is a simple log monitoring application that reads log files, extracts relevant information, and provides a user-friendly interface for monitoring logs in real-time.

Setup Instructions
------------------
1. Clone the repository:
   ```bash
    git clone
    ```
   
2. Navigate to the project directory:
   ```bash
   cd log-monitoring
   ```
3. Install the required dependencies:
   ```bash
   composer install
   ``` 
   
4. Run the command that parses the log files:
   ```bash
   php artisan log:monitoring:parse-file logs.log
   ```

