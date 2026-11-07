<?php
// Variables esperadas:
// $name, $email, $subject, $message
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body{
            margin:0;
            padding:0;
            background:#0f172a;
            font-family:Arial, Helvetica, sans-serif;
        }

        @media only screen and (max-width: 700px){

            .container{
                width:100% !important;
            }

            .content-padding{
                padding:30px 20px !important;
            }

            .info-label{
                display:block !important;
                width:100% !important;
                padding-bottom:6px !important;
            }

            .info-value{
                display:block !important;
                width:100% !important;
                padding-bottom:18px !important;
            }

            .title{
                font-size:38px !important;
            }

            .subtitle{
                font-size:16px !important;
                line-height:26px !important;
            }
        }
    </style>
</head>

<body>

<table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center" style="padding:40px 15px;">

            <!-- CONTAINER -->
            <table
                class="container"
                width="650"
                cellpadding="0"
                cellspacing="0"
                border="0"
                style="
                    width:650px;
                    max-width:650px;
                    background:#161b22;
                    border-radius:20px;
                    overflow:hidden;
                "
            >

                <!-- HEADER -->
                <tr>
                    <td
                        align="center"
                        style="
                            background:linear-gradient(135deg,#0057ff,#0037a6);
                            padding:60px 30px;
                        "
                    >

                        <div
                            style="
                                display:inline-block;
                                background:rgba(255,255,255,0.12);
                                color:#ffffff;
                                padding:12px 24px;
                                border-radius:999px;
                                font-size:14px;
                                font-weight:bold;
                                letter-spacing:2px;
                                margin-bottom:30px;
                            "
                        >
                            CONTACT FORM
                        </div>

                        <h1
                            class="title"
                            style="
                                margin:0;
                                color:#ffffff;
                                font-size:52px;
                                font-weight:bold;
                                line-height:1.1;
                            "
                        >
                            Nuevo mensaje
                        </h1>

                        <p
                            class="subtitle"
                            style="
                                margin:25px 0 0;
                                color:#dbe7ff;
                                font-size:18px;
                                line-height:30px;
                                max-width:500px;
                            "
                        >
                            Has recibido una nueva consulta desde el formulario de contacto de Zentra.
                        </p>

                    </td>
                </tr>

                <!-- BODY -->
                <tr>
                    <td
                        class="content-padding"
                        style="padding:50px 45px;"
                    >

                        <!-- TITLE -->
                        <h2
                            style="
                                margin:0 0 35px;
                                color:#ffffff;
                                font-size:40px;
                                line-height:1.2;
                            "
                        >
                            Información del cliente
                        </h2>

                        <!-- INFO BOX -->
                        <table
                            width="100%"
                            cellpadding="0"
                            cellspacing="0"
                            border="0"
                            style="
                                background:#1f2430;
                                border:1px solid #2d3748;
                                border-radius:16px;
                                padding:30px;
                            "
                        >

                            <!-- NOMBRE -->
                            <tr>
                                <td
                                    class="info-label"
                                    width="160"
                                    style="
                                        width:160px;
                                        padding:14px 10px 14px 0;
                                        color:#8ea2ff;
                                        font-weight:bold;
                                        font-size:18px;
                                        vertical-align:top;
                                    "
                                >
                                    Nombre
                                </td>

                                <td
                                    class="info-value"
                                    style="
                                        padding:14px 0;
                                        color:#e5e7eb;
                                        font-size:18px;
                                        line-height:28px;
                                        word-break:break-word;
                                    "
                                >
                                    <?= htmlspecialchars($name ?? '') ?>
                                </td>
                            </tr>

                            <!-- EMAIL -->
                            <tr>
                                <td
                                    class="info-label"
                                    width="160"
                                    style="
                                        width:160px;
                                        padding:14px 10px 14px 0;
                                        color:#8ea2ff;
                                        font-weight:bold;
                                        font-size:18px;
                                        vertical-align:top;
                                    "
                                >
                                    Email
                                </td>

                                <td
                                    class="info-value"
                                    style="
                                        padding:14px 0;
                                        color:#60a5fa;
                                        font-size:18px;
                                        line-height:28px;
                                        word-break:break-all;
                                    "
                                >
                                    <?= htmlspecialchars($email ?? '') ?>
                                </td>
                            </tr>

                            <!-- ASUNTO -->
                            <tr>
                                <td
                                    class="info-label"
                                    width="160"
                                    style="
                                        width:160px;
                                        padding:14px 10px 14px 0;
                                        color:#8ea2ff;
                                        font-weight:bold;
                                        font-size:18px;
                                        vertical-align:top;
                                    "
                                >
                                    Asunto
                                </td>

                                <td
                                    class="info-value"
                                    style="
                                        padding:14px 0;
                                        color:#e5e7eb;
                                        font-size:18px;
                                        line-height:28px;
                                        word-break:break-word;
                                    "
                                >
                                    <?= htmlspecialchars($subject ?? '') ?>
                                </td>
                            </tr>

                            <!-- FECHA -->
                            <tr>
                                <td
                                    class="info-label"
                                    width="160"
                                    style="
                                        width:160px;
                                        padding:14px 10px 0 0;
                                        color:#8ea2ff;
                                        font-weight:bold;
                                        font-size:18px;
                                        vertical-align:top;
                                    "
                                >
                                    Fecha
                                </td>

                                <td
                                    class="info-value"
                                    style="
                                        padding:14px 0 0;
                                        color:#e5e7eb;
                                        font-size:18px;
                                        line-height:28px;
                                    "
                                >
                                    <?= date('d/m/Y H:i') ?>
                                </td>
                            </tr>

                        </table>

                        <!-- MENSAJE -->
                        <h2
                            style="
                                margin:45px 0 25px;
                                color:#ffffff;
                                font-size:38px;
                                line-height:1.2;
                            "
                        >
                            Mensaje
                        </h2>

                        <table
                            width="100%"
                            cellpadding="0"
                            cellspacing="0"
                            border="0"
                            style="
                                background:#1f2430;
                                border:1px solid #2d3748;
                                border-left:6px solid #4f6cff;
                                border-radius:16px;
                            "
                        >
                            <tr>
                                <td
                                    style="
                                        padding:30px;
                                        color:#e5e7eb;
                                        font-size:18px;
                                        line-height:34px;
                                        word-break:break-word;
                                    "
                                >
                                    <?= nl2br(htmlspecialchars($message ?? '')) ?>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                <!-- FOOTER -->
                <tr>
                    <td
                        align="center"
                        style="
                            padding:30px;
                            background:#111827;
                            color:#94a3b8;
                            font-size:14px;
                            line-height:24px;
                        "
                    >
                        © <?= date('Y') ?> Zentra · Todos los derechos reservados
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>