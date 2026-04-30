<?php
/**
 * @var string $name
 * @var string $email
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Bienvenido a VitaeX</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f7f6; font-family: 'Inter', 'Segoe UI', Arial, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f7f6; padding: 40px 0;">
    <tr>
        <td align="center">

            <!-- Tarjeta principal -->
            <table width="580" cellpadding="0" cellspacing="0"
                   style="background-color:#ffffff; border-radius:12px; overflow:hidden;
                 box-shadow: 0 10px 25px rgba(0,0,0,0.05); max-width:580px; width:100%;">

                <!-- Franja superior (Morado para bienvenida) -->
                <tr>
                    <td style="background-color:#8b5cf6; height:6px; font-size:0;">&nbsp;</td>
                </tr>

                <tr>
                    <td style="padding: 40px 48px 30px 48px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="vertical-align:middle;">
                                    <div style="display:inline-block; background-color:#8b5cf6; border-radius:8px;
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
                        <h2 style="margin:0 0 15px 0; font-size:26px; font-weight:800;
                          color:#111827; letter-spacing:-1px;">
                            ¡Bienvenido a VitaeX, <?= htmlspecialchars($name) ?>!
                        </h2>
                        <p style="margin:0; font-size:16px; color:#4b5563; line-height:1.7;">
                            Tu cuenta ha sido creada exitosamente. Estamos emocionados de acompañarte en tu crecimiento profesional 
                            y conectarte con las mejores oportunidades laborales en Nayarit.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 20px 48px 40px 48px;">
                        <div style="background-color:#fdfaff; border-radius:12px; padding: 30px;
                           border: 1px solid #f5f0ff;">
                            <p style="margin:0 0 15px 0; font-size:14px; font-weight:700; color:#6d28d9;
                            text-transform:uppercase; letter-spacing:1px;">
                                Próximos pasos
                            </p>
                            <ul style="margin:0; padding:0 0 0 20px; color:#4b5563; font-size:15px; line-height:2;">
                                <li>Completa tu perfil profesional</li>
                                <li>Sube tu currículum actualizado</li>
                                <li>Realiza las evaluaciones diagnósticas</li>
                                <li>Explora y postúlate a vacantes</li>
                            </ul>
                        </div>
                        
                        <div style="margin-top: 35px; text-align:center;">
                            <a href="<?= Env::get('FRONTEND_URL', 'http://localhost:4200') ?>" 
                               style="background-color:#8b5cf6; color:#ffffff; padding: 14px 28px; 
                                      border-radius:8px; text-decoration:none; font-weight:700; 
                                      display:inline-block; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);">
                                Acceder a mi Panel
                            </a>
                        </div>
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
