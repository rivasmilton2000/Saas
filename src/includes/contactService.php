<?php
require_once __DIR__ . '/../config/app.php';
//Connection Database
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/mail.php';

// PHP Mailer librery
// PHPMailer se carga desde el autoloader de Composer del proyecto.

// Llamando variables del .env
require_once __DIR__ . '/../config/env.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// Enviar correo electrónico
function sendMail($name, $email, $subject, $message)
{
    if (!mailIsConfigured()) {
        return false;
    }
    $config = mailConfig();
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username = $config['username']; // Correo electronico de la aplicacion
        $mail->Password = $config['password']; // Contraseña de aplicacion
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($config['from'], $config['from_name']);
        $mail->addReplyTo($email, $name);
        $mail->addAddress((string)envValue('MAIL_CONTACT_TO', $config['from']));

        $mail->isHTML(true);
        $mail->Subject = "Consulta: $subject";

        ob_start();
        require __DIR__ . '/../partials/emails/messageDesign.php';
        $mail->Body = ob_get_clean();

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}


// Alertas de Bootstrap
function alertaVolver($title, $message, $icon)
{
  // Memoria temporal del usuario
    $_SESSION['alert'] = [
        'title' => $title,
        'message' => $message,
        'icon' => $icon
    ];

    header('Location: ' . saasUrl('public/contact.php'));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    // Limpiar datos
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validaciones
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        alertaVolver("Error", "No dejes los campos vacíos", "warning");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        alertaVolver("Error", "Email inválido", "warning");
    }

    // INSERT seguro de los datos del contacto
    $stmt = $pdo->prepare("
        INSERT INTO mensajes_contacto
        (nombre, email, asunto, mensaje)
        VALUES (?, ?, ?, ?)
    ");

    if ($stmt->execute([$name, $email, $subject, $message])) {
        if (sendMail($name, $email, $subject, $message)) {
            alertaVolver("Éxito", "Correo enviado correctamente", "success");
            //header("Location: " . saasUrl("public/contact.php"));
            //exit;
        } else {
            alertaVolver("Error", "Error al enviar el correo", "error");
        }
    } else {
        alertaVolver("Error", "Error al guardar el mensaje", "error");
    }
}
?>
