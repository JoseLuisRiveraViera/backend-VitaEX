<?php
/**
 * @var string $code
 * @var string $email
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Recuperar Contraseña - VitaeX</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f7f6; font-family: 'Inter', 'Segoe UI', Arial, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f7f6; padding: 40px 0;">
    <tr>
        <td align="center">

            <!-- Tarjeta principal -->
            <table width="580" cellpadding="0" cellspacing="0"
                   style="background-color:#ffffff; border-radius:12px; overflow:hidden;
                 box-shadow: 0 10px 25px rgba(0,0,0,0.05); max-width:580px; width:100%;">

                <!-- Franja superior (Azul para diferenciar de 2FA) -->
                <tr>
                    <td style="background-color:#3b82f6; height:6px; font-size:0;">&nbsp;</td>
                </tr>

                <tr>
                    <td style="padding: 40px 48px 30px 48px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="vertical-align:middle;">
                                    <div style="display:inline-block; background-color:#3b82f6; border-radius:8px;
                                width:44px; height:44px; text-align:center; line-height:44px;
                                font-size:20px; color:#ffffff; font-weight:bold;">
                                        VX
                                    </div>
                                </td>
                                <td style="vertical-align:middle; padding-left:16px;">
                                    <p style="margin:0; font-size:12px; font-weight:700; color:#6b7280;
                                text-transform:uppercase; letter-spacing:1.5px; line-height:1;">
                                        Bolsa de Trabajo
                                    </p>
                                    <p style="margin:4px 0 0 0; font-size:16px; font-weight:800; color:#111827;
                                letter-spacing:0.5px; line-height:1.2;">
                                        Universidad Tecnológica de la Costa
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 0 48px;">
                        <div style="height:1px; background-color:#e5e7eb;"></div>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 35px 48px 20px 48px;">
                        <h2 style="margin:0 0 15px 0; font-size:24px; font-weight:800;
                          color:#111827; letter-spacing:-0.5px;">
                            Recuperación de Contraseña
                        </h2>
                        <p style="margin:0; font-size:15px; color:#4b5563; line-height:1.6;">
                            Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>VitaeX</strong>. 
                            Utiliza el siguiente código para continuar con el proceso.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 25px 48px 40px 48px;">
                        <div style="background-color:#f0f7ff; border-radius:12px; padding: 35px 24px;
                           text-align:center; border: 1px solid #e0efff;">
                            <p style="margin:0 0 12px 0; font-size:12px; font-weight:700; color:#60a5fa;
                            text-transform:uppercase; letter-spacing:3px;">
                                CÓDIGO DE RECUPERACIÓN
                            </p>
                            <p style="margin:0; font-size:48px; font-weight:900; color:#1d4ed8;
                            letter-spacing:12px; line-height:1;">
                                <?= htmlspecialchars($code) ?>
                            </p>
                            <p style="margin:20px 0 0 0; font-size:12px; color:#6b7280;">
                                Válido por <strong>15 minutos</strong>.
                            </p>
                        </div>
                        
                        <p style="margin:30px 0 0 0; font-size:13px; color:#9ca3af; text-align:center;">
                            Si no solicitaste este cambio, ignora este correo. Tu contraseña actual no se verá afectada.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background-color:#f9fafb; padding: 25px 48px; border-top: 1px solid #f3f4f6;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size:12px; color:#9ca3af; line-height:1.5;">
                                    &copy; <?= date('Y') ?> UT de la Costa. Nayarit, México.<br>
                                    Plataforma de Vinculación Profesional.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
