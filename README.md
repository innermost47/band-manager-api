# Band Manager - Backend

## 🎼 Overview

Backend API for Band Manager application, built with Symfony 6. This API handles all the business logic, data management, and authentication for the Band Manager platform.

## 🌟 Features

- RESTful API endpoints
- File upload management
- User authentication and authorization
- Project management
- Administrative tools

## 🚀 Getting Started

### Prerequisites

- PHP 8.3 or higher
- Composer
- MySQL 8.0 or higher
- Symfony CLI

### Installation

```bash
# Clone the repository
git clone https://github.com/innermost47/band-manager-api.git

# Navigate to project directory
cd band-manager-api

# Install dependencies
composer install

# Create database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate

# Load fixtures
php bin/console doctrine:fixtures:load

# Create upload dirs
mkdir -p var/uploads/private/{audio,project_images,documents}

# Start server
symfony server:start
```

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📜 License

This project is licensed under the MIT License.
