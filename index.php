<?php
// Include database connection
include 'db.php';

// Start session
session_start();

// Check if user is logged in
$loggedIn = isset($_SESSION['user_id']);
$username = $loggedIn ? $_SESSION['username'] : '';

// Handle login/signup form submissions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        // Login logic
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $success = "Login successful!";
                $loggedIn = true;
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "User not found!";
        }
        $stmt->close();
    } elseif (isset($_POST['signup'])) {
        // Signup logic
        $username = $_POST['username'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Username already exists!";
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $username, $hashed_password);
                
                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $success = "Account created successfully!";
                    $loggedIn = true;
                } else {
                    $error = "Error creating account: " . $conn->error;
                }
            }
            $stmt->close();
        }
    } elseif (isset($_POST['logout'])) {
        // Logout logic
        session_destroy();
        $loggedIn = false;
        // Use JavaScript to redirect to the same page (refresh)
        echo "<script>window.location.href = 'index.php';</script>";
        exit;
    }
}

// Save chat history to database if user is logged in
if ($loggedIn && isset($_POST['save_chat'])) {
    $chat_content = $_POST['chat_content'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("INSERT INTO chat_history (user_id, content, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $user_id, $chat_content);
    
    if ($stmt->execute()) {
        $success = "Chat saved successfully!";
    } else {
        $error = "Error saving chat: " . $conn->error;
    }
    $stmt->close();
}

// Get chat history if user is logged in
$chat_history = [];
if ($loggedIn) {
    $user_id = $_SESSION['user_id'];
    $result = $conn->query("SELECT content, created_at FROM chat_history WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10");
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $chat_history[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Annie - AI Assistant</title>
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
            color: #fff;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Header Styles */
        header {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e94560;
        }
        
        .logo h1 {
            font-size: 1.8rem;
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: 700;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 2rem;
        }
        
        nav a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            position: relative;
            padding: 0.5rem 0;
        }
        
        nav a:hover {
            color: #e94560;
        }
        
        nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            transition: width 0.3s;
        }
        
        nav a:hover::after {
            width: 100%;
        }
        
        .auth-buttons button {
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 50px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-left: 1rem;
        }
        
        .auth-buttons button:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(233, 69, 96, 0.4);
        }
        
        /* Hero Section */
        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .hero-content {
            flex: 1;
            max-width: 600px;
        }
        
        .hero h2 {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .hero p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
            color: #ccc;
        }
        
        .cta-button {
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            color: #fff;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            display: inline-block;
            text-decoration: none;
        }
        
        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(233, 69, 96, 0.4);
        }
        
        .hero-image {
            flex: 1;
            display: flex;
            justify-content: center;
            position: relative;
        }
        
        .robot-container {
            width: 300px;
            height: 400px;
            position: relative;
        }
        
        .robot {
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 120"><rect x="30" y="10" width="40" height="30" rx="5" fill="%23e94560"/><circle cx="50" cy="60" r="30" fill="%23333"/><circle cx="40" cy="50" r="5" fill="%23fff"/><circle cx="60" cy="50" r="5" fill="%23fff"/><rect x="40" y="70" width="20" height="5" rx="2" fill="%23fff"/><rect x="20" y="80" width="60" height="30" rx="5" fill="%23444"/><rect x="15" y="40" width="10" height="30" rx="3" fill="%23555"/><rect x="75" y="40" width="10" height="30" rx="3" fill="%23555"/></svg>') no-repeat center;
            background-size: contain;
            position: absolute;
            top: 0;
            left: 0;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .glow {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(233, 69, 96, 0.3) 0%, rgba(233, 69, 96, 0) 70%);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.1); }
        }
        
        /* Chat Interface */
        .chat-container {
            max-width: 1000px;
            margin: 2rem auto;
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            height: 600px;
        }
        
        .chat-header {
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .chat-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
        }
        
        .chat-header h3 {
            font-size: 1.2rem;
            color: #fff;
        }
        
        .chat-messages {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .message {
            max-width: 80%;
            padding: 1rem;
            border-radius: 15px;
            position: relative;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .user-message {
            align-self: flex-end;
            background: linear-gradient(90deg, #0f3460, #16213e);
            border-bottom-right-radius: 0;
        }
        
        .bot-message {
            align-self: flex-start;
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            border-bottom-left-radius: 0;
        }
        
        .message p {
            margin: 0;
            line-height: 1.5;
        }
        
        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.5rem;
            text-align: right;
        }
        
        .chat-input {
            padding: 1rem;
            background-color: rgba(0, 0, 0, 0.2);
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .chat-input input {
            flex: 1;
            padding: 1rem;
            border-radius: 50px;
            border: none;
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 1rem;
        }
        
        .chat-input input:focus {
            outline: none;
            box-shadow: 0 0 0 2px #e94560;
        }
        
        .chat-input button {
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .chat-input button:hover {
            transform: scale(1.1);
        }
        
        .chat-input button svg {
            width: 20px;
            height: 20px;
            fill: #fff;
        }
        
        /* Voice Controls */
        .voice-controls {
            display: flex;
            gap: 1rem;
        }
        
        .voice-button {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .voice-button:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .voice-button.recording {
            background: #e94560;
            animation: pulse 1s infinite;
        }
        
        .voice-button svg {
            width: 20px;
            height: 20px;
            fill: #fff;
        }
        
        /* Features Section */
        .features {
            padding: 4rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: inline-block;
        }
        
        .section-title p {
            color: #ccc;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            padding: 2rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            border-radius: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .feature-icon svg {
            width: 30px;
            height: 30px;
            fill: #fff;
        }
        
        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .feature-card p {
            color: #ccc;
            line-height: 1.6;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            border-radius: 20px;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: modalFadeIn 0.5s ease-out;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: #e94560;
        }
        
        .modal-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            text-align: center;
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background-color: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #e94560;
        }
        
        .form-submit {
            width: 100%;
            padding: 1rem;
            border-radius: 10px;
            border: none;
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .form-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(233, 69, 96, 0.4);
        }
        
        .form-switch {
            text-align: center;
            margin-top: 1rem;
        }
        
        .form-switch a {
            color: #e94560;
            text-decoration: none;
            cursor: pointer;
        }
        
        .form-switch a:hover {
            text-decoration: underline;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            animation: fadeIn 0.3s ease-out;
        }
        
        .alert-error {
            background-color: rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 0, 0, 0.3);
            color: #ff6b6b;
        }
        
        .alert-success {
            background-color: rgba(0, 255, 0, 0.1);
            border: 1px solid rgba(0, 255, 0, 0.3);
            color: #69db7c;
        }
        
        /* Footer */
        footer {
            background-color: rgba(0, 0, 0, 0.5);
            padding: 3rem 2rem;
            margin-top: 4rem;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .footer-logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e94560;
        }
        
        .footer-logo h3 {
            font-size: 1.5rem;
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .footer-about p {
            color: #ccc;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.3s, transform 0.3s;
        }
        
        .social-link:hover {
            background-color: #e94560;
            transform: translateY(-3px);
        }
        
        .social-link svg {
            width: 20px;
            height: 20px;
            fill: #fff;
        }
        
        .footer-links h4, .footer-contact h4 {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .footer-links h4::after, .footer-contact h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: linear-gradient(90deg, #e94560, #ff9b6a);
        }
        
        .footer-links ul {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 0.8rem;
        }
        
        .footer-links a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s;
            display: inline-block;
        }
        
        .footer-links a:hover {
            color: #e94560;
            transform: translateX(5px);
        }
        
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #ccc;
        }
        
        .contact-item svg {
            width: 20px;
            height: 20px;
            fill: #e94560;
        }
        
        .copyright {
            text-align: center;
            padding-top: 2rem;
            margin-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #ccc;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .hero {
                flex-direction: column;
                text-align: center;
                gap: 3rem;
            }
            
            .hero-content {
                max-width: 100%;
            }
            
            nav ul {
                gap: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .auth-buttons {
                margin-top: 1rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .hero h2 {
                font-size: 2rem;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .chat-container {
                height: 500px;
                margin: 1rem;
            }
            
            .message {
                max-width: 90%;
            }
        }
        
        /* Animation for typing indicator */
        .typing-indicator {
            display: flex;
            gap: 0.5rem;
            padding: 1rem;
            background: rgba(233, 69, 96, 0.2);
            border-radius: 15px;
            width: fit-content;
            margin-top: 0.5rem;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            background-color: #fff;
            border-radius: 50%;
            opacity: 0.7;
        }
        
        .typing-dot:nth-child(1) {
            animation: typingDot 1.5s infinite 0s;
        }
        
        .typing-dot:nth-child(2) {
            animation: typingDot 1.5s infinite 0.3s;
        }
        
        .typing-dot:nth-child(3) {
            animation: typingDot 1.5s infinite 0.6s;
        }
        
        @keyframes typingDot {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        /* Chat History Styles */
        .chat-history-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .chat-history-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .chat-history-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .chat-history-item {
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            transition: transform 0.3s;
        }
        
        .chat-history-item:hover {
            transform: translateY(-5px);
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .chat-history-date {
            font-size: 0.8rem;
            color: #ccc;
            margin-bottom: 0.5rem;
        }
        
        .chat-history-content {
            line-height: 1.6;
        }
        
        .no-history {
            text-align: center;
            color: #ccc;
            padding: 2rem;
        }
        
        /* Loading Animation */
        .loading {
            display: inline-block;
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto;
        }
        
        .loading div {
            position: absolute;
            top: 33px;
            width: 13px;
            height: 13px;
            border-radius: 50%;
            background: linear-gradient(90deg, #e94560, #ff9b6a);
            animation-timing-function: cubic-bezier(0, 1, 1, 0);
        }
        
        .loading div:nth-child(1) {
            left: 8px;
            animation: loading1 0.6s infinite;
        }
        
        .loading div:nth-child(2) {
            left: 8px;
            animation: loading2 0.6s infinite;
        }
        
        .loading div:nth-child(3) {
            left: 32px;
            animation: loading2 0.6s infinite;
        }
        
        .loading div:nth-child(4) {
            left: 56px;
            animation: loading3 0.6s infinite;
        }
        
        @keyframes loading1 {
            0% { transform: scale(0); }
            100% { transform: scale(1); }
        }
        
        @keyframes loading2 {
            0% { transform: translate(0, 0); }
            100% { transform: translate(24px, 0); }
        }
        
        @keyframes loading3 {
            0% { transform: scale(1); }
            100% { transform: scale(0); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="logo">
            <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%23e94560'/><circle cx='35' cy='40' r='5' fill='%23fff'/><circle cx='65' cy='40' r='5' fill='%23fff'/><path d='M35 70 Q50 80 65 70' stroke='%23fff' stroke-width='3' fill='none'/></svg>" alt="Call Annie Logo">
            <h1>Call Annie</h1>
        </div>
        <nav>
            <ul>
                <li><a href="#features">Features</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#chat">Try It Now</a></li>
            </ul>
        </nav>
        <div class="auth-buttons">
            <?php if ($loggedIn): ?>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="logout" value="1">
                    <button type="submit">Logout</button>
                </form>
                <button id="profile-btn">Profile</button>
            <?php else: ?>
                <button id="login-btn">Login</button>
                <button id="signup-btn">Sign Up</button>
            <?php endif; ?>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h2>Meet Annie, Your AI Assistant</h2>
            <p>Experience the future of conversation with our advanced AI assistant. Annie can answer questions, provide information, and engage in natural conversations through text or voice.</p>
            <a href="#chat" class="cta-button">Start Chatting Now</a>
        </div>
        <div class="hero-image">
            <div class="robot-container">
                <div class="glow"></div>
                <div class="robot"></div>
            </div>
        </div>
    </section>

    <!-- Chat Interface -->
    <section id="chat" class="chat-container">
        <div class="chat-header">
            <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%23e94560'/><circle cx='35' cy='40' r='5' fill='%23fff'/><circle cx='65' cy='40' r='5' fill='%23fff'/><path d='M35 70 Q50 80 65 70' stroke='%23fff' stroke-width='3' fill='none'/></svg>" alt="Annie Avatar">
            <h3>Annie</h3>
        </div>
        <div class="chat-messages" id="chat-messages">
            <div class="message bot-message">
                <p>Hi there! I'm Annie, your AI assistant. How can I help you today?</p>
                <div class="message-time">Just now</div>
            </div>
        </div>
        <div class="chat-input">
            <input type="text" id="user-input" placeholder="Type your message here...">
            <div class="voice-controls">
                <button class="voice-button" id="voice-btn" title="Voice Input">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
                        <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
                    </svg>
                </button>
            </div>
            <button id="send-btn">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
            </button>
        </div>
    </section>

    <?php if ($loggedIn && !empty($chat_history)): ?>
    <!-- Chat History Section -->
    <section class="chat-history-container">
        <h2 class="chat-history-title">Your Chat History</h2>
        <div class="chat-history-list">
            <?php foreach ($chat_history as $chat): ?>
            <div class="chat-history-item">
                <div class="chat-history-date"><?php echo date('F j, Y, g:i a', strtotime($chat['created_at'])); ?></div>
                <div class="chat-history-content"><?php echo htmlspecialchars($chat['content']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="section-title">
            <h2>Amazing Features</h2>
            <p>Discover what makes Call Annie the perfect AI assistant for your needs.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                    </svg>
                </div>
                <h3>Natural Conversations</h3>
                <p>Engage in fluid, human-like conversations with our advanced AI that understands context and nuance.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 15c1.66 0 2.99-1.34 2.99-3L15 6c0-1.66-1.34-3-3-3S9 4.34 9 6v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 15 6.7 12H5c0 3.42 2.72 6.23 6 6.72V22h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/>
                    </svg>
                </div>
                <h3>Voice Interaction</h3>
                <p>Speak directly to Annie and hear responses in a natural voice, making interaction seamless and convenient.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
                    </svg>
                </div>
                <h3>Intelligent Assistance</h3>
                <p>Get help with information, tasks, and questions with an AI that learns and adapts to your needs.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                    </svg>
                </div>
                <h3>Secure & Private</h3>
                <p>Your conversations are encrypted and your data is kept private, ensuring your information stays secure.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM19 18H6c-2.21 0-4-1.79-4-4 0-2.05 1.53-3.76 3.56-3.97l1.07-.11.5-.95C8.08 7.14 9.94 6 12 6c2.62 0 4.88 1.86 5.39 4.43l.3 1.5 1.53.11c1.56.1 2.78 1.41 2.78 2.96 0 1.65-1.35 3-3 3z"/>
                    </svg>
                </div>
                <h3>Cloud-Based</h3>
                <p>Access Annie from anywhere, on any device, with our cloud-based platform that syncs your conversations.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <h3>Always Learning</h3>
                <p>Annie continuously improves through machine learning, getting better at understanding and assisting you over time.</p>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="features">
        <div class="section-title">
            <h2>How It Works</h2>
            <p>Understanding the technology behind Call Annie.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                </div>
                <h3>Step 1: Input</h3>
                <p>Speak or type your message to Annie through our intuitive interface.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                    </svg>
                </div>
                <h3>Step 2: Processing</h3>
                <p>Our advanced AI analyzes your message using natural language processing to understand your intent.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14zM8 15c0-1.66 1.34-3 3-3 .35 0 .69.07 1 .18V6h5v2h-3v7.03c-.02 1.64-1.35 2.97-3 2.97-1.66 0-3-1.34-3-3z"/>
                    </svg>
                </div>
                <h3>Step 3: Response</h3>
                <p>Annie generates a thoughtful, contextually relevant response based on your input and previous conversations.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-about">
                <div class="footer-logo">
                    <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%23e94560'/><circle cx='35' cy='40' r='5' fill='%23fff'/><circle cx='65' cy='40' r='5' fill='%23fff'/><path d='M35 70 Q50 80 65 70' stroke='%23fff' stroke-width='3' fill='none'/></svg>" alt="Call Annie Logo">
                    <h3>Call Annie</h3>
                </div>
                <p>Experience the future of conversation with our advanced AI assistant. Annie can answer questions, provide information, and engage in natural conversations.</p>
                <div class="social-links">
                    <a href="#" class="social-link">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22.675 0H1.325C.593 0 0 .593 0 1.325v21.351C0 23.407.593 24 1.325 24H12.82v-9.294H9.692v-3.622h3.128V8.413c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12V24h6.116c.73 0 1.323-.593 1.323-1.325V1.325C24 .593 23.407 0 22.675 0z"/>
                        </svg>
                    </a>
                    <a href="#" class="social-link">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M23.954 4.569c-.885.389-1.83.654-2.825.775 1.014-.611 1.794-1.574 2.163-2.723-.951.555-2.005.959-3.127 1.184-.896-.959-2.173-1.559-3.591-1.559-2.717 0-4.92 2.203-4.92 4.917 0 .39.045.765.127 1.124C7.691 8.094 4.066 6.13 1.64 3.161c-.427.722-.666 1.561-.666 2.475 0 1.71.87 3.213 2.188 4.096-.807-.026-1.566-.248-2.228-.616v.061c0 2.385 1.693 4.374 3.946 4.827-.413.111-.849.171-1.296.171-.314 0-.615-.03-.916-.086.631 1.953 2.445 3.377 4.604 3.417-1.68 1.319-3.809 2.105-6.102 2.105-.39 0-.779-.023-1.17-.067 2.189 1.394 4.768 2.209 7.557 2.209 9.054 0 14-7.503 14-14 0-.21-.005-.418-.015-.628.961-.689 1.8-1.56 2.46-2.548l-.047-.02z"/>
                        </svg>
                    </a>
                    <a href="#" class="social-link">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                        </svg>
                    </a>
                </div>
            </div>
            <div class="footer-links">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#">Home</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#chat">Try It Now</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                </ul>
            </div>
            <div class="footer-contact">
                <h4>Contact Us</h4>
                <div class="contact-info">
                    <div class="contact-item">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        <span>123 AI Street, Tech City, TC 12345</span>
                    </div>
                    <div class="contact-item">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        <span>contact@callannie.com</span>
                    </div>
                    <div class="contact-item">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                        </svg>
                        <span>+1 (555) 123-4567</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; 2023 Call Annie. All rights reserved.</p>
        </div>
    </footer>

    <!-- Login Modal -->
    <div class="modal" id="login-modal">
        <div class="modal-content">
            <button class="close-modal">&times;</button>
            <h2 class="modal-title">Login to Call Annie</h2>
            <?php if ($error && isset($_POST['login'])): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success && isset($_POST['login'])): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="login-username">Username</label>
                    <input type="text" id="login-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                <button type="submit" name="login" class="form-submit">Login</button>
                <div class="form-switch">
                    <p>Don't have an account? <a id="switch-to-signup">Sign Up</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Signup Modal -->
    <div class="modal" id="signup-modal">
        <div class="modal-content">
            <button class="close-modal">&times;</button>
            <h2 class="modal-title">Create an Account</h2>
            <?php if ($error && isset($_POST['signup'])): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success && isset($_POST['signup'])): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="signup-username">Username</label>
                    <input type="text" id="signup-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="signup-password">Password</label>
                    <input type="password" id="signup-password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="signup-confirm-password">Confirm Password</label>
                    <input type="password" id="signup-confirm-password" name="confirm_password" required>
                </div>
                <button type="submit" name="signup" class="form-submit">Sign Up</button>
                <div class="form-switch">
                    <p>Already have an account? <a id="switch-to-login">Login</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Profile Modal -->
    <?php if ($loggedIn): ?>
    <div class="modal" id="profile-modal">
        <div class="modal-content">
            <button class="close-modal">&times;</button>
            <h2 class="modal-title">Your Profile</h2>
            <div style="text-align: center; margin-bottom: 2rem;">
                <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%23e94560'/><circle cx='35' cy='40' r='5' fill='%23fff'/><circle cx='65' cy='40'  r='45' fill='%23e94560'/><circle cx='35' cy='40' r='5' fill='%23fff'/><circle cx='65' cy='40' r='5' fill='%23fff'/><path d='M35 70 Q50 80 65 70' stroke='%23fff' stroke-width='3' fill='none'/></svg>" alt="Profile Picture" style="width: 100px; height: 100px; border-radius: 50%; border: 3px solid #e94560;">
                <h3 style="margin-top: 1rem; font-size: 1.5rem;"><?php echo htmlspecialchars($username); ?></h3>
            </div>
            <div style="background-color: rgba(255, 255, 255, 0.05); padding: 1.5rem; border-radius: 15px; margin-bottom: 1.5rem;">
                <h4 style="margin-bottom: 1rem; color: #e94560;">Account Information</h4>
                <p style="margin-bottom: 0.5rem;"><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
                <p style="margin-bottom: 0.5rem;"><strong>Member Since:</strong> <?php echo date('F j, Y'); ?></p>
                <p><strong>Conversations:</strong> <?php echo count($chat_history); ?></p>
            </div>
            <form method="post">
                <input type="hidden" name="logout" value="1">
                <button type="submit" class="form-submit">Logout</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Save Chat Modal -->
    <?php if ($loggedIn): ?>
    <div class="modal" id="save-chat-modal">
        <div class="modal-content">
            <button class="close-modal">&times;</button>
            <h2 class="modal-title">Save Conversation</h2>
            <?php if ($error && isset($_POST['save_chat'])): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success && isset($_POST['save_chat'])): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="chat-content">Conversation Content</label>
                    <textarea id="chat-content" name="chat_content" rows="10" style="width: 100%; padding: 1rem; border-radius: 10px; background-color: rgba(255, 255, 255, 0.05); color: #fff; border: 1px solid rgba(255, 255, 255, 0.1);"></textarea>
                </div>
                <button type="submit" name="save_chat" class="form-submit">Save Conversation</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // DOM Elements
        const loginBtn = document.getElementById('login-btn');
        const signupBtn = document.getElementById('signup-btn');
        const profileBtn = document.getElementById('profile-btn');
        const loginModal = document.getElementById('login-modal');
        const signupModal = document.getElementById('signup-modal');
        const profileModal = document.getElementById('profile-modal');
        const saveChatModal = document.getElementById('save-chat-modal');
        const switchToSignup = document.getElementById('switch-to-signup');
        const switchToLogin = document.getElementById('switch-to-login');
        const closeModalBtns = document.querySelectorAll('.close-modal');
        const chatMessages = document.getElementById('chat-messages');
        const userInput = document.getElementById('user-input');
        const sendBtn = document.getElementById('send-btn');
        const voiceBtn = document.getElementById('voice-btn');
        
        // Chat variables
        let isRecording = false;
        let recognition;
        let chatContent = '';
        
        // Modal functions
        function openModal(modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Event Listeners for Modals
        if (loginBtn) {
            loginBtn.addEventListener('click', () => openModal(loginModal));
        }
        
        if (signupBtn) {
            signupBtn.addEventListener('click', () => openModal(signupModal));
        }
        
        if (profileBtn) {
            profileBtn.addEventListener('click', () => openModal(profileModal));
        }
        
        if (switchToSignup) {
            switchToSignup.addEventListener('click', () => {
                closeModal(loginModal);
                openModal(signupModal);
            });
        }
        
        if (switchToLogin) {
            switchToLogin.addEventListener('click', () => {
                closeModal(signupModal);
                openModal(loginModal);
            });
        }
        
        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const modal = btn.closest('.modal');
                closeModal(modal);
            });
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                closeModal(e.target);
            }
        });
        
        // Chat functions
        function addMessage(message, isUser = false) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message');
            messageDiv.classList.add(isUser ? 'user-message' : 'bot-message');
            
            const messageText = document.createElement('p');
            messageText.textContent = message;
            
            const messageTime = document.createElement('div');
            messageTime.classList.add('message-time');
            messageTime.textContent = 'Just now';
            
            messageDiv.appendChild(messageText);
            messageDiv.appendChild(messageTime);
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Update chat content for saving
            chatContent += (isUser ? 'You: ' : 'Annie: ') + message + '\n';
            
            return messageDiv;
        }
        
        function showTypingIndicator() {
            const typingDiv = document.createElement('div');
            typingDiv.classList.add('typing-indicator');
            typingDiv.id = 'typing-indicator';
            
            for (let i = 0; i < 3; i++) {
                const dot = document.createElement('div');
                dot.classList.add('typing-dot');
                typingDiv.appendChild(dot);
            }
            
            chatMessages.appendChild(typingDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            return typingDiv;
        }
        
        function removeTypingIndicator() {
            const typingIndicator = document.getElementById('typing-indicator');
            if (typingIndicator) {
                typingIndicator.remove();
            }
        }
        
        async function getAIResponse(userMessage) {
            try {
                const typingIndicator = showTypingIndicator();
                
                // Prepare the request to the Gemini API
                const apiKey = 'AIzaSyCS1xSEgDXOrtJuB4F1InEQOlP0nywNB3o';
                const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${apiKey}`;
                
                const requestData = {
                    contents: [{
                        parts: [{text: userMessage}]
                    }]
                };
                
                // Make the API request
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestData)
                });
                
                const data = await response.json();
                
                // Remove typing indicator
                removeTypingIndicator();
                
                // Check if we have a valid response
                if (data.candidates && data.candidates.length > 0 && 
                    data.candidates[0].content && 
                    data.candidates[0].content.parts && 
                    data.candidates[0].content.parts.length > 0) {
                    
                    const aiResponse = data.candidates[0].content.parts[0].text;
                    
                    // Add AI response to chat
                    addMessage(aiResponse);
                    
                    // Speak the response if speech synthesis is available
                    if ('speechSynthesis' in window) {
                        const speech = new SpeechSynthesisUtterance(aiResponse);
                        speech.rate = 1;
                        speech.pitch = 1;
                        speech.volume = 1;
                        window.speechSynthesis.speak(speech);
                    }
                    
                } else {
                    // Handle error or empty response
                    addMessage("I'm sorry, I couldn't process your request at the moment. Please try again later.");
                }
                
            } catch (error) {
                console.error('Error getting AI response:', error);
                removeTypingIndicator();
                addMessage("I'm sorry, there was an error processing your request. Please try again later.");
            }
        }
        
        // Send message when clicking send button
        sendBtn.addEventListener('click', () => {
            const message = userInput.value.trim();
            if (message) {
                addMessage(message, true);
                userInput.value = '';
                getAIResponse(message);
            }
        });
        
        // Send message when pressing Enter
        userInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                const message = userInput.value.trim();
                if (message) {
                    addMessage(message, true);
                    userInput.value = '';
                    getAIResponse(message);
                }
            }
        });
        
        // Voice recognition
        voiceBtn.addEventListener('click', () => {
            if (!isRecording) {
                // Check if browser supports speech recognition
                if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                    // Initialize speech recognition
                    recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
                    recognition.continuous = false;
                    recognition.interimResults = false;
                    
                    // Start recording
                    recognition.start();
                    isRecording = true;
                    voiceBtn.classList.add('recording');
                    
                    // Handle results
                    recognition.onresult = (event) => {
                        const transcript = event.results[0][0].transcript;
                        userInput.value = transcript;
                        
                        // Stop recording
                        recognition.stop();
                        isRecording = false;
                        voiceBtn.classList.remove('recording');
                        
                        // Send message
                        if (transcript.trim()) {
                            addMessage(transcript, true);
                            userInput.value = '';
                            getAIResponse(transcript);
                        }
                    };
                    
                    // Handle errors
                    recognition.onerror = (event) => {
                        console.error('Speech recognition error:', event.error);
                        isRecording = false;
                        voiceBtn.classList.remove('recording');
                    };
                    
                    // Handle end of recording
                    recognition.onend = () => {
                        isRecording = false;
                        voiceBtn.classList.remove('recording');
                    };
                } else {
                    alert('Speech recognition is not supported in your browser. Please try Chrome or Edge.');
                }
            } else {
                // Stop recording
                recognition.stop();
                isRecording = false;
                voiceBtn.classList.remove('recording');
            }
        });
        
        // Save chat functionality
        <?php if ($loggedIn): ?>
        // Add save button to chat interface
        const chatInput = document.querySelector('.chat-input');
        const saveBtn = document.createElement('button');
        saveBtn.id = 'save-btn';
        saveBtn.title = 'Save Conversation';
        saveBtn.innerHTML = `
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
            </svg>
        `;
        saveBtn.style.background = 'rgba(255, 255, 255, 0.1)';
        
        chatInput.insertBefore(saveBtn, chatInput.firstChild);
        
        // Open save chat modal when clicking save button
        saveBtn.addEventListener('click', () => {
            document.getElementById('chat-content').value = chatContent;
            openModal(saveChatModal);
        });
        <?php endif; ?>
        
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 100,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Robot animation
        const robot = document.querySelector('.robot');
        if (robot) {
            // Add subtle movement to robot
            setInterval(() => {
                const randomX = Math.random() * 10 - 5;
                const randomY = Math.random() * 10 - 5;
                robot.style.transform = `translate(${randomX}px, ${randomY}px)`;
            }, 2000);
        }
    </script>
</body>
</html>
