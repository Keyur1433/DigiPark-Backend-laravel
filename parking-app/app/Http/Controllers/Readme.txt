Register User: 
1. http://localhost:8000/api/auth/register
{
    "first_name": "Kenil",
    "last_name": "Lakhani",
    "email": "johndoe@example.com",
    "contact_number": "9876543210",
    "password": "SecurePass123",
    "password_confirmation": "SecurePass123",
    "state": "California",
    "city": "Los Angeles",
    "country": "USA",
    "role": "user"
}

response:
{
    "message": "User registered successfully. Please verify your account with the OTP sent to your mobile number.",
    "user": {
        "id": 1,
        "first_name": "Kenil",
        "last_name": "Lakhani",
        "email": "johndoe@example.com",
        "contact_number": "9876543210",
        "state": "California",
        "city": "Los Angeles",
        "country": "USA",
        "role": "user",
        "is_verified": false,
        "created_at": "2025-04-01T17:26:39.000000Z",
        "updated_at": "2025-04-01T17:26:39.000000Z"
    }
}

2. http://localhost:8000/api/auth/verify-otp
{
    "contact_number": "9876543210",
    "otp": "377196",
    "type": "registration"
}

3. {{url}}/api/auth/login
{
    "contact_number": "9876543210",
    "password": "NewSecurePass123"
}