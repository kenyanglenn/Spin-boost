# SpinBoost Game

A fun spinning game with wallet system and payment integration.

## Local Development

1. Install XAMPP or similar PHP/MySQL environment
2. Import `spinboost_schema.sql` to create the database
3. Update database credentials in `db.php` if needed
4. Run on localhost

## Deployment to Railway

1. **Create GitHub Repository:**
   - Go to GitHub.com and create a new repository
   - Use GitHub Desktop to push this code to the repository

2. **Deploy to Railway:**
   - Go to [Railway.app](https://railway.app) and sign up/login
   - Click "New Project" → "Deploy from GitHub repo"
   - Connect your GitHub account and select the repository
   - Railway will automatically detect PHP and set up MySQL

3. **Database Setup:**
   - Railway will create a MySQL database automatically
   - The database credentials will be available in Railway environment variables
   - Import `spinboost_schema.sql` to set up tables (you can do this via Railway's database interface)

4. **Environment Variables:**
   - Railway automatically provides database credentials
   - No manual configuration needed for database connection

5. **Payment Integration:**
   - Once deployed, get your live URL from Railway
   - Use this URL to apply for payment provider credentials (Paystack, Flutterwave, etc.)

## Features

- User registration and login
- Wallet system with deposits
- Spinning game mechanics
- Payment processing
- Admin analytics

## Technologies

- PHP 8.1+
- MySQL
- HTML/CSS/JavaScript
- PDO for database operations