# Event Ticketing and Venue Booking System

## Project Overview

The Event Ticketing and Venue Booking System is a web-based system developed using a RESTful API architecture. The system allows administrators and organisers to manage events, venues, schedules, and ticket availability, while customers can browse events, make bookings, cancel bookings, and view their booking status.

This project uses PHP for backend API development, MySQL for database management, HTML/CSS/JavaScript for frontend development, and JWT for authentication.

## Technologies Used

- PHP
- MySQL
- HTML
- CSS
- JavaScript
- RESTful API
- JSON Web Token (JWT)
- XAMPP
- Postman
- Composer
- GitHub

## Main API Resources

The system contains four main API resources:

- `/users`
- `/events`
- `/venues`
- `/bookings`

## User Roles

The system supports the following roles:

- Administrator
- Organiser
- Staff
- Customer

Role-Based Access Control (RBAC) is used to restrict access to protected resources.

## Authentication

The system uses JSON Web Token (JWT) authentication.

Users must log in using valid credentials. After successful authentication, a JWT token is generated and used to access protected API endpoints.

## Member A - Database, Users and Authentication

Member A is responsible for:

- Database design and ERD
- DDL and DML scripts
- Database connection
- Users REST API
- User registration
- User login
- JWT authentication
- Role-Based Access Control
- User management frontend
- GitHub repository setup and documentation

### Users API

The Users API supports:

- GET - Retrieve users
- POST - Create a new user
- PUT - Update an existing user
- DELETE - Delete a user

Access to user management is restricted to the Administrator role.

## Project Structure

event_booking/
- backend/
  - db.php
  - register.php
  - login.php
  - auth.php
  - users.php
- database/
  - database.sql
- frontend_a/
  - login.html
  - users.html
  - style.css
- composer.json
- composer.lock
- README.md

## API Testing

Postman is used to test the REST API.

Testing includes:

- Successful login
- Invalid login
- JWT authentication
- Missing or invalid token
- Role-Based Access Control
- GET users
- POST user
- PUT user
- DELETE user
- Input validation
- Error responses

## Database

The system uses the following main tables:

1. Users
2. Events
3. Venues
4. Bookings

Each table contains at least five sample records for testing.

## Security Features

- Password hashing
- JWT authentication
- Role-Based Access Control
- Protected API endpoints
- Input validation
- HTTP status codes