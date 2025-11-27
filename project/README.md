# 📦 Blog Platform - Installation Guide

## 🚀 Quick Start

### Prerequisites
- Docker Desktop installed
- Git
- 8GB RAM minimum

### Installation Steps

```bash
# 1. Navigate to project folder
cd project

# 2. Start Docker containers
docker-compose up -d

# 3. Wait for containers to start (30-60 seconds)
docker ps

# 4. Install backend dependencies
docker exec -it blog_backend composer install

# 5. Setup Laravel
docker exec -it blog_backend cp env.example .env
docker exec -it blog_backend php artisan key:generate

# 6. Run migrations and seeders
docker exec -it blog_backend php artisan migrate:fresh --seed

# 7. Install frontend dependencies (if needed)
docker exec -it blog_frontend npm install
```

### 🌐 Access the Application

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000/api
- **MySQL**: localhost:3306

---

## 🛠️ Useful Commands

### Backend Commands

```bash
# Access backend container
docker exec -it blog_backend bash

# Run migrations
docker exec -it blog_backend php artisan migrate

# Seed database
docker exec -it blog_backend php artisan db:seed

# Clear cache
docker exec -it blog_backend php artisan cache:clear

# View logs
docker logs blog_backend -f
```

### Frontend Commands

```bash
# Access frontend container
docker exec -it blog_frontend sh

# Rebuild frontend
docker exec -it blog_frontend npm run build

# View logs
docker logs blog_frontend -f
```

### Database Commands

```bash
# Access MySQL
docker exec -it blog_mysql mysql -u blog_user -p
# Password: blog_password

# Backup database
docker exec blog_mysql mysqldump -u blog_user -pblog_password blog_db > backup.sql

# Restore database
docker exec -i blog_mysql mysql -u blog_user -pblog_password blog_db < backup.sql
```

---

## 🔄 Restart / Stop

```bash
# Stop all containers
docker-compose down

# Stop and remove volumes (⚠️ deletes database)
docker-compose down -v

# Restart containers
docker-compose restart

# Rebuild containers (after Dockerfile changes)
docker-compose up -d --build
```

---

## 📁 Project Structure

```
/project/
├── docker-compose.yml          # Docker orchestration
├── backend/                    # Laravel API
│   ├── app/
│   │   ├── Models/            # Database models
│   │   ├── Http/Controllers/  # API controllers
│   │   └── ...
│   ├── database/
│   │   ├── migrations/        # Database schema
│   │   └── seeders/           # Sample data
│   ├── routes/api.php         # API routes
│   └── .env.example
├── frontend/                   # React application
│   ├── src/
│   │   ├── components/        # React components
│   │   ├── services/          # API calls
│   │   └── App.jsx
│   └── package.json
└── README.md                   # This file
```

## 📞 Need Help?

If you're stuck:
1. Check Docker logs: `docker logs blog_backend -f`
2. Verify all containers are running: `docker ps`
3. Check the main CHALLENGE.md for more details

---

Good luck! 🚀

