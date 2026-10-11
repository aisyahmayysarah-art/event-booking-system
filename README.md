# Event Ticketing and Venue Booking System

## 1. Project Overview

The Event Ticketing and Venue Booking System is a web-based application developed using RESTful API architecture to simplify event management, venue administration, and ticket booking processes.

The system provides role-based functionality for Administrators, Organisers, Staff, and Customers. Administrators can manage users and venues and monitor system records. Organisers can create and manage events, control ticket availability, and view relevant customer bookings. Customers can browse published events, check ticket availability, make bookings, cancel eligible bookings, and view their booking history.

The application integrates a PHP backend, MySQL database, and HTML, CSS, and JavaScript frontend. JSON Web Token (JWT) authentication and Role-Based Access Control (RBAC) are implemented to protect restricted operations.

The system also integrates SendGrid for automated booking confirmation and cancellation email notifications.

## 2. Technologies Used

- PHP – Backend RESTful API development
- MySQL – Relational database management
- HTML – Frontend structure
- CSS – Frontend styling
- JavaScript – Frontend functionality and API integration
- RESTful API – Communication between frontend and backend
- JSON Web Token (JWT) – User authentication
- Role-Based Access Control (RBAC) – User permission management
- XAMPP – Local Apache and MySQL environment
- Postman – API testing and verification
- Composer – PHP dependency management
- SendGrid API – Automated email notifications
- Git and GitHub – Version control and repository management
- Visual Studio Code – Development environment

## 3. Main API Resources

The system provides four main RESTful API resources:

| Resource | PHP Endpoint | Description |
|---|---|---|
| Users | `backend/users.php` | User account management |
| Events | `backend/events.php` | Event management |
| Venues | `backend/venues.php` | Venue management |
| Bookings | `backend/bookings.php` | Ticket booking and cancellation |

Authentication is handled through `backend/login.php`.

API responses use JSON and appropriate HTTP status codes.

## 4. User Roles and Permissions

The system supports four user roles:

### Administrator

- Manage registered user accounts.
- Add, update, and delete user records.
- Manage venue information.
- View event, venue, and booking records.
- Monitor system information according to administrative permissions.

### Organiser

- Create new events.
- Update and delete events owned by the Organiser.
- Manage event schedules, ticket prices, and ticket availability.
- View bookings associated with owned events.
- Search and filter relevant booking records.

### Staff

- Access operational functions permitted by the backend.
- View relevant event and booking information according to assigned permissions.

### Customer

- Browse published events.
- View event details and available tickets.
- Create ticket bookings.
- Cancel eligible bookings.
- View personal booking details and statuses.

RBAC is enforced through backend permission checks to prevent unauthorised operations.

## 5. Authentication and Security

The system uses JWT authentication to protect restricted API endpoints.

After successful login, the backend generates a JWT containing the authenticated user's information. The token is supplied through the Authorization header for protected API requests.

Example:

`Authorization: Bearer YOUR_JWT_TOKEN`

The system implements the following security features:

- Password hashing and verification
- JWT-based authentication
- Authentication middleware
- Role-Based Access Control
- Protected API endpoints
- Request validation
- Prepared SQL statements
- HTTP error response handling
- Environment-based configuration of sensitive API credentials

The JWT signing secret is configured through the `JWT_SECRET` environment variable.

The SendGrid API key is configured through the `SENDGRID_API_KEY` environment variable.

Actual API keys, JWT signing secrets, and database passwords must not be committed to GitHub.

## 6. Database Design

The application uses a MySQL database named `event_booking_db`.

It contains four main tables:

| Table | Description |
|---|---|
| Users | Stores user details, credentials, and roles |
| Events | Stores event information, schedules, ticket prices, and availability |
| Venues | Stores venue details, locations, capacities, and statuses |
| Bookings | Stores ticket booking information, quantities, prices, and statuses |

The database uses primary and foreign keys to establish relationships between users, events, venues, and bookings.

The project includes SQL scripts and exported database records for local setup and testing.

## 7. RESTful API Functions

### 7.1 Users API

File: `backend/users.php`

Supported operations:

- GET – Retrieve user records
- GET – Retrieve a user by ID
- POST – Create a new user
- PUT – Update an existing user
- DELETE – Delete a user

User management operations are restricted according to backend role permissions.

### 7.2 Events API

File: `backend/events.php`

Supported operations:

- GET – Retrieve event records
- POST – Create a new event
- PUT – Update an existing event
- DELETE – Delete an event

Organisers can manage their own events and monitor ticket availability.

### 7.3 Venues API

File: `backend/venues.php`

Supported operations:

- GET – Retrieve venue records
- POST – Create a new venue
- PUT – Update venue details
- DELETE – Delete a venue

Venue operations include input validation and permission checks.

### 7.4 Bookings API

File: `backend/bookings.php`

Supported operations:

- GET – Retrieve permitted booking records
- POST – Create a ticket booking
- PUT – Cancel an eligible booking

The Bookings API supports:

- JWT authentication
- Role-based access
- Ticket availability updates
- Booking status management
- Booking search and filtering
- Pagination
- SendGrid email notifications

Customers can view their own bookings, while Organisers can view bookings associated with their events.

## 8. Individual Frontend Implementation

The individual frontend was developed using HTML, CSS, and JavaScript and integrated with the shared PHP RESTful APIs.

It includes a common login interface and role-based dashboards.

### 8.1 Login Interface

File: `frontend_a/login.html`

- Authenticate registered users.
- Process login responses.
- Handle JWT authentication.
- Redirect users according to their assigned roles.
- Provide logout functionality.

### 8.2 Administrator Dashboard

Files: `frontend_a/users.html` and `frontend_a/venues.html`

- Add, update, and delete users.
- Display registered users and their roles.
- Manage venue information.
- View event records.
- View venue records.
- View booking records.

### 8.3 Organiser Dashboard

File: `frontend_a/organiser.html`

- Create new events.
- Edit and delete owned events.
- Display event schedules and ticket availability.
- Search event records.
- View relevant customer bookings.
- Search and filter booking records.
- Navigate booking records using pagination.

### 8.4 Customer Dashboard

File: `frontend_a/customer.html`

- Browse published events.
- Check ticket availability.
- Select ticket quantities.
- Make ticket bookings.
- View booking history.
- Cancel eligible bookings.
- View booking statuses.

The frontend communicates with the backend APIs using JavaScript HTTP requests.

## 9. SendGrid Email Integration

SendGrid is integrated into the Bookings API to provide automated email notifications.

### Booking Confirmation Email

After a successful booking, the system sends a confirmation email containing booking information such as:

- Booking reference
- Event name
- Ticket quantity
- Total price
- Booking status

### Booking Cancellation Email

When an eligible booking is cancelled, the system sends a cancellation notification containing the relevant booking information and updated status.

### SendGrid Configuration

The integration requires:

1. A SendGrid account.
2. A verified sender identity.
3. A valid SendGrid API key with Mail Send permission.
4. The `SENDGRID_API_KEY` environment variable configured in Apache/XAMPP.
5. An Apache restart after updating the environment configuration.

Booking confirmation and cancellation emails were successfully received during testing.

The API key is not stored directly in the PHP source code.

## 10. Project Structure

```text
event_booking/
|
|-- backend/
|   |-- auth.php
|   |-- bookings.php
|   |-- db.php
|   |-- events.php
|   |-- login.php
|   |-- users.php
|   |-- venues.php
|
|-- database/
|   |-- database.sql
|   |-- event_booking_db.sql
|
|-- frontend_a/
|   |-- customer.html
|   |-- login.html
|   |-- organiser.html
|   |-- style.css
|   |-- users.html
|   |-- venues.html
|
|-- postman/
|   |-- Event_Booking_API.postman_collection.json
|
|-- .gitignore
|-- composer.json
|-- composer.lock
|-- README.md
```

The project separates backend logic, database scripts, frontend interfaces, and API testing resources for easier development and maintenance.

## 11. Running the System Locally

### Step 1: Install the Required Software

Install:

- XAMPP
- Visual Studio Code
- Composer
- Postman
- Git

### Step 2: Set Up the Project Folder

Clone the repository or copy the project folder into:

`C:\xampp\htdocs\event_booking`

For an XAMPP installation on another drive, use the corresponding `htdocs` directory.

### Step 3: Start XAMPP

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### Step 4: Import the Database

1. Open `http://localhost/phpmyadmin`.
2. Create a database named `event_booking_db`.
3. Select the database.
4. Open the Import tab.
5. Import `database/event_booking_db.sql`.
6. Verify that the Users, Events, Venues, and Bookings tables are available.

### Step 5: Configure the Database Connection

Open `backend/db.php` and ensure the database connection settings match the local MySQL configuration.

Avoid committing real database credentials to the repository.

### Step 6: Install PHP Dependencies

Open a terminal in the project directory and run:

```bash
composer install
```

### Step 7: Configure Environment Variables

Configure a secure JWT signing secret in the Apache environment:

`JWT_SECRET`

For SendGrid email functionality, configure:

`SENDGRID_API_KEY`

Restart Apache after making configuration changes.

### Step 8: Run the Frontend

Open:

`http://localhost/event_booking/frontend_a/login.html`

Log in using an existing account from the imported database.

The system will provide access to the relevant dashboard according to the user's role.

## 12. API Testing with Postman

Postman was used to test the system's RESTful APIs.

The exported collection is available at:

`postman/Event_Booking_API.postman_collection.json`

Testing covers:

- Successful login
- Invalid login
- JWT authentication
- Missing or invalid authentication tokens
- Role-Based Access Control
- Users CRUD operations
- Events CRUD operations
- Venues CRUD operations
- Booking creation
- Booking cancellation
- Input validation
- Error responses
- HTTP status codes

The testing process verifies communication between the frontend, PHP backend, and MySQL database.

## 13. System Integration and Verification

The integrated system was tested locally using XAMPP.

The following functions were successfully demonstrated:

- Administrator user management
- Administrator system records monitoring
- Venue management
- Organiser event creation
- Organiser event ownership restrictions
- Organiser booking record viewing
- Customer event browsing
- Customer ticket booking
- Customer booking cancellation
- Ticket availability updates
- JWT authentication and RBAC
- SendGrid booking confirmation emails
- SendGrid booking cancellation emails

An event created through the Organiser dashboard was successfully displayed in the Customer dashboard. After a Customer booked a ticket, the available ticket count was updated and the booking appeared in the Organiser's relevant booking records.

This demonstrates successful integration between the different frontend roles, backend APIs, and database.

## 14. Debugging and Improvements

Several issues were identified and resolved during development.

### Authentication and Access Control

JWT authentication and role-based permission checks were tested to ensure that restricted API operations could not be performed without the required access.

### SendGrid Email Delivery

An initial HTTP 403 response occurred because the sender email address was not verified.

The issue was resolved by completing SendGrid Sender Identity Verification and updating the sender configuration.

### GitHub Secret Protection

A repository update was rejected because a SendGrid API key had been included in the Git commit history.

The hardcoded key was removed from the application source code, and the affected pending commits were rebuilt before the updated project was successfully pushed.

Sensitive configuration values are now intended to be managed through environment variables rather than committed source code.

### Booking Record Management

Pagination, searching, and status filtering were implemented to make booking records easier to retrieve and manage.

## 15. Project Repository

GitHub Repository:

https://github.com/aisyahmayysarah-art/event-booking-system

The repository contains the integrated backend, database scripts, individual frontend, and exported Postman collection.

Git was used to manage source code changes, integrate project components, and maintain development history.

## 16. Individual Contribution – Member A

Member A contributed to the following components:

- Database design and configuration
- SQL DDL and DML scripts
- Database connection using PHP PDO
- Users RESTful API
- User authentication and login
- JWT authentication and middleware
- Role-Based Access Control
- Postman API testing
- Administrator frontend
- Organiser frontend
- Customer frontend
- Frontend-backend integration
- SendGrid email integration and testing
- GitHub repository management
- Project documentation
- Debugging and integration verification

## 17. Conclusion

The Event Ticketing and Venue Booking System integrates RESTful APIs, a relational MySQL database, role-based frontend dashboards, and automated email notifications into a functional web application.

The system supports user management, event management, venue administration, ticket booking, and booking cancellation. JWT authentication and RBAC help protect restricted operations, while SendGrid provides automated booking notifications.

The project demonstrates practical implementation of backend API development, frontend integration, database management, authentication, third-party API integration, testing, debugging, and collaborative software development.