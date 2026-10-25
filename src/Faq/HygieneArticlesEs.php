<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/Configuration-glpi-auto
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Configurationglpiauto\Faq;

/**
 * Issue #321: end-user security good-practice articles, Spanish translation of
 * HygieneArticlesFr (same order).
 */
final class HygieneArticlesEs
{
    public const ARTICLES = [
        [
            'name' => 'Buenas prácticas: elegir una contraseña robusta',
            'answer' => <<<'HTML'
                <p>Una contraseña robusta es, ante todo, una contraseña <strong>larga</strong>: la longitud importa más que los caracteres complicados.</p>
                <h4>El método más sencillo: la frase de contraseña</h4>
                <p>Junte cuatro o cinco palabras que no tengan relación entre sí, por ejemplo «jirafa-tambor-tormenta-limón». Es fácil de recordar y muy difícil de adivinar. Evite las frases célebres, las letras de canciones y todo lo que tenga que ver con usted (nombres, fechas de nacimiento, nombre de la empresa).</p>
                <h4>Las reglas que debe recordar</h4>
                <ul>
                  <li>al menos 14 caracteres, o cuatro palabras;</li>
                  <li>una contraseña <strong>distinta para cada servicio</strong>: si un sitio sufre un ataque, sus otras cuentas siguen protegidas;</li>
                  <li>no reutilice nunca su contraseña del trabajo en un sitio personal.</li>
                </ul>
                <h4>El gestor de contraseñas</h4>
                <p>Nadie puede recordar decenas de contraseñas distintas. Un gestor de contraseñas las recuerda por usted y solo tiene que memorizar una contraseña principal. Pregunte al soporte cuál está autorizado en la empresa.</p>
                HTML,
        ],
        [
            'name' => 'Buenas prácticas: no compartir nunca su contraseña',
            'answer' => <<<'HTML'
                <p>Su contraseña es personal: demuestra que es usted quien actúa. Todo lo que se haga con su cuenta se le atribuye a usted.</p>
                <h4>Nadie necesita su contraseña</h4>
                <ul>
                  <li><strong>El soporte informático nunca se la pedirá</strong>, ni por teléfono, ni por correo electrónico, ni por mensaje. Los técnicos disponen de sus propias herramientas para intervenir.</li>
                  <li>Un compañero que necesite un archivo o un acceso debe solicitarlo para sí mismo: no le preste su sesión.</li>
                  <li>Su responsable tampoco: una ausencia se gestiona con una delegación o compartiendo una carpeta, no comunicando una contraseña.</li>
                </ul>
                <h4>Si alguien se la pide</h4>
                <p>Probablemente se trate de un intento de estafa. No responda y notifíquelo al soporte desde el portal («Notificar una incidencia»).</p>
                <h4>Si la ha comunicado por error</h4>
                <p>Cámbiela inmediatamente y avise al soporte: notificarlo no es una falta, es lo correcto.</p>
                HTML,
        ],
        [
            'name' => 'Buenas prácticas: bloquear la sesión en cuanto se aleje de su puesto',
            'answer' => <<<'HTML'
                <p>Un equipo que se deja abierto, aunque sea dos minutos, permite que cualquiera lea sus mensajes, envíe un correo electrónico en su nombre o copie archivos.</p>
                <h4>El gesto correcto, cada vez que se levante</h4>
                <ul>
                  <li>En Windows: pulse la tecla <strong>Windows + L</strong>.</li>
                  <li>En Mac: pulse <strong>Ctrl + Cmd + Q</strong>.</li>
                </ul>
                <p>Su trabajo sigue abierto: basta con volver a escribir su contraseña al regresar.</p>
                <h4>Además</h4>
                <ul>
                  <li>No deje su portátil sin vigilancia en un tren, una cafetería o una sala de reuniones.</li>
                  <li>Guarde los documentos en papel confidenciales antes de irse por la tarde.</li>
                  <li>El bloqueo automático al cabo de unos minutos es una medida de seguridad, no una molestia: no intente desactivarlo.</li>
                </ul>
                HTML,
        ],
        [
            'name' => 'Buenas prácticas: por qué no es administrador de su equipo',
            'answer' => <<<'HTML'
                <p>Su cuenta no tiene derechos de administración sobre su ordenador. No es falta de confianza: es el principio del <strong>mínimo privilegio</strong>, una de las protecciones más eficaces.</p>
                <h4>Para qué sirve</h4>
                <p>Un programa malicioso actúa con los derechos de la persona que lo ha abierto. Sin derechos de administración, no puede instalarse a fondo, ni desactivar el antivirus, ni propagarse con facilidad. Así, un simple clic por error tiene consecuencias limitadas.</p>
                <h4>Necesita instalar un programa o cambiar un ajuste</h4>
                <ul>
                  <li>Haga la solicitud desde el portal, en el catálogo de servicios («Solicitar un servicio»).</li>
                  <li>Indique el programa o el ajuste, y por qué lo necesita.</li>
                  <li>El soporte lo instala por usted, o le concede un derecho <strong>temporal</strong> si está justificado.</li>
                </ul>
                <p>No instale nunca un programa encontrado en Internet para saltarse esta limitación: es precisamente lo que buscan los atacantes.</p>
                HTML,
        ],
        [
            'name' => 'Buenas prácticas: la autenticación multifactor',
            'answer' => <<<'HTML'
                <p>La autenticación multifactor añade una segunda prueba a su contraseña: un código recibido en su teléfono, una notificación que debe aprobar o una llave de seguridad. Aunque alguien robe su contraseña, no podrá conectarse sin este segundo elemento.</p>
                <h4>Los buenos hábitos</h4>
                <ul>
                  <li>No apruebe <strong>nunca</strong> una solicitud de conexión que no haya iniciado usted mismo, aunque se repita: es señal de que alguien conoce su contraseña. Rechácela y notifíquelo al soporte.</li>
                  <li>No comunique nunca a nadie un código recibido por SMS o por la aplicación, ni siquiera a alguien que se presente como el soporte.</li>
                </ul>
                <h4>Ha perdido o cambiado de teléfono</h4>
                <p>Avise al soporte <strong>el mismo día</strong>, desde el portal o por teléfono. Desactivará el dispositivo antiguo y le ayudará a registrar el nuevo. No espere a quedarse bloqueado para notificarlo.</p>
                HTML,
        ],
        [
            'name' => 'Buenas prácticas: reconocer un phishing y notificarlo',
            'answer' => <<<'HTML'
                <p>El phishing (suplantación de identidad) es un mensaje que imita a un remitente de confianza para que usted haga clic en un enlace, abra un archivo adjunto o facilite una contraseña. Es la principal puerta de entrada de los ataques.</p>
                <h4>Las señales que deben alertarle</h4>
                <ul>
                  <li>una sensación de <strong>urgencia</strong>: «su cuenta se eliminará hoy», «pago atrasado»;</li>
                  <li>una dirección de remitente que se parece a la auténtica, pero no del todo;</li>
                  <li>un enlace cuya dirección real (visible al pasar el ratón por encima sin hacer clic) no coincide con el sitio esperado;</li>
                  <li>una petición inusual: cambiar unos datos bancarios, comprar tarjetas regalo, comunicar un código;</li>
                  <li>un archivo adjunto inesperado, incluso de un contacto conocido.</li>
                </ul>
                <h4>Qué hacer</h4>
                <ul>
                  <li>No haga clic ni abra el archivo adjunto.</li>
                  <li>Si tiene dudas, contacte con el remitente por otro medio (su número habitual, no el que aparece en el mensaje).</li>
                  <li>Notifique el mensaje al soporte desde el portal («Notificar una incidencia»).</li>
                </ul>
                <p>¿Ya ha hecho clic o ha introducido su contraseña? Avise al soporte <strong>inmediatamente</strong>: unos minutos pueden marcar la diferencia.</p>
                HTML,
        ],
        [
            'name' => 'Buenas prácticas: memorias USB y soportes extraíbles',
            'answer' => <<<'HTML'
                <p>Una memoria USB puede contener un programa malicioso que se ejecuta en cuanto se conecta. Además, se pierde con facilidad, junto con los datos que contiene.</p>
                <h4>Las reglas que debe seguir</h4>
                <ul>
                  <li>No conecte <strong>nunca</strong> una memoria que haya encontrado o recibido sin haberla pedido: entréguela al soporte.</li>
                  <li>Utilice únicamente los soportes proporcionados o autorizados por la empresa.</li>
                  <li>No copie datos de la empresa en una memoria personal.</li>
                  <li>Para intercambiar archivos, utilice preferentemente los espacios compartidos de la empresa en lugar de una memoria USB.</li>
                </ul>
                <h4>Si pierde una memoria o un disco</h4>
                <p>Notifíquelo al soporte sin demora, indicando qué contenía. Si había datos personales o confidenciales, la empresa puede estar obligada a declararlo en plazos breves.</p>
                HTML,
        ],
        [
            'name' => 'Buenas prácticas: no aplazar las actualizaciones',
            'answer' => <<<'HTML'
                <p>Las actualizaciones corrigen fallos de seguridad que los atacantes conocen y a veces aprovechan desde el día siguiente a su publicación. Un equipo sin actualizar es una puerta abierta.</p>
                <h4>Lo que le pedimos</h4>
                <ul>
                  <li>Cuando su ordenador le proponga reiniciar para terminar una actualización, hágalo lo antes posible, por ejemplo durante una pausa o al final de la jornada.</li>
                  <li>No aplace una actualización varios días seguidos.</li>
                  <li>Actualice también su teléfono del trabajo y sus aplicaciones.</li>
                </ul>
                <h4>Si una actualización causa problemas</h4>
                <p>¿Un programa ha dejado de funcionar o el equipo va muy lento después de reiniciar? Notifíquelo al soporte desde el portal en lugar de intentar deshacer los cambios usted mismo.</p>
                HTML,
        ],
        [
            'name' => 'Buenas prácticas: teletrabajo, wifi público y VPN',
            'answer' => <<<'HTML'
                <p>Fuera de la oficina, sus comunicaciones pasan por redes que la empresa no controla. Unas sencillas reglas las protegen.</p>
                <h4>La VPN</h4>
                <p>La VPN crea un túnel cifrado entre su ordenador y la red de la empresa. <strong>Conéctela siempre</strong> antes de acceder a las aplicaciones y los archivos de la empresa.</p>
                <h4>Las redes wifi públicas</h4>
                <ul>
                  <li>En una estación, un hotel o una cafetería, una wifi abierta puede ser espiada o imitada por un punto de acceso falso.</li>
                  <li>Utilice preferentemente la conexión compartida de su teléfono del trabajo.</li>
                  <li>Si utiliza una wifi pública, active la VPN antes que nada.</li>
                </ul>
                <h4>En casa y de viaje</h4>
                <ul>
                  <li>No deje que un familiar o amigo utilice su equipo del trabajo.</li>
                  <li>Cuidado con las miradas y los oídos indiscretos en el transporte y los lugares públicos: puede solicitar al soporte un filtro de privacidad para la pantalla.</li>
                </ul>
                HTML,
        ],
        [
            'name' => 'Buenas prácticas: dónde guardar sus documentos',
            'answer' => <<<'HTML'
                <p>Un documento guardado únicamente en su ordenador desaparece con él en caso de avería, robo o ransomware. Los espacios de la empresa, en cambio, cuentan con copias de seguridad.</p>
                <h4>Dónde guardar</h4>
                <ul>
                  <li>sus documentos de trabajo: en su espacio personal de la empresa o en las carpetas compartidas de su departamento;</li>
                  <li>no en el escritorio ni en la carpeta «Descargas» de su equipo, que normalmente no tienen copia de seguridad;</li>
                  <li>nunca en un servicio de almacenamiento personal (cuenta privada en línea, correo electrónico personal).</li>
                </ul>
                <h4>Ha eliminado o sobrescrito un archivo por error</h4>
                <p>Si estaba en un espacio con copia de seguridad, el soporte a menudo puede restaurarlo. Haga la solicitud rápidamente desde el portal indicando el nombre del archivo, su ubicación y la fecha en la que todavía estaba bien.</p>
                HTML,
        ],
        [
            'name' => 'Buenas prácticas: datos personales en el día a día (RGPD)',
            'answer' => <<<'HTML'
                <p>Los datos personales son toda la información que permite identificar a una persona: nombre, dirección, teléfono, número de cliente, fotografía, datos de salud o de nómina. El RGPD obliga a protegerlos.</p>
                <h4>En el día a día</h4>
                <ul>
                  <li>Acceda únicamente a los datos que necesite para su trabajo.</li>
                  <li>No envíe archivos de clientes o de empleados a una dirección personal, ni a través de un servicio no autorizado.</li>
                  <li>Compruebe los destinatarios antes de enviar un correo electrónico que contenga datos personales.</li>
                  <li>No guarde copias «por si acaso»: elimine lo que ya no sea útil.</li>
                  <li>Imprima lo menos posible y recoja sus impresiones enseguida.</li>
                </ul>
                <h4>En caso de error</h4>
                <p>Un correo electrónico enviado al destinatario equivocado, un archivo compartido con demasiadas personas, un ordenador perdido: avise al soporte inmediatamente. A veces, una fuga de datos personales debe notificarse a la autoridad de control en un plazo de 72 horas.</p>
                HTML,
        ],
        [
            'name' => 'Buenas prácticas: qué hacer en caso de incidente de seguridad',
            'answer' => <<<'HTML'
                <p>Un incidente de seguridad es, por ejemplo: un equipo anormalmente lento o que muestra ventanas desconocidas, archivos que se han vuelto ilegibles, un mensaje que exige un rescate, un correo electrónico enviado en su nombre, la pérdida o el robo de un ordenador o de un teléfono.</p>
                <h4>Los buenos hábitos</h4>
                <ul>
                  <li><strong>Avise al soporte inmediatamente</strong>, desde el portal («Notificar una incidencia») o por teléfono si ya no puede utilizar su equipo.</li>
                  <li>Mensaje de rescate o archivos ilegibles: <strong>desconecte el cable de red y desactive la wifi</strong>, pero no apague el ordenador sin instrucciones del soporte.</li>
                  <li>No pague nunca un rescate y no responda a los atacantes.</li>
                  <li>No intente repararlo usted mismo y no elimine nada: los rastros ayudan a entender lo que ha ocurrido.</li>
                  <li>Anote la hora y lo que ha visto; una captura de pantalla hecha con su teléfono puede ser de ayuda.</li>
                </ul>
                <h4>Notificar no es motivo de vergüenza</h4>
                <p>Cualquiera puede caer en una trampa. Lo que cuenta es la rapidez: un incidente notificado en pocos minutos causa muchos menos daños que uno descubierto días después.</p>
                HTML,
        ],
    ];
}
