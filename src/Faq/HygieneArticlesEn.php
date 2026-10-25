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
 * Issue #321: end-user security good-practice articles, English translation of HygieneArticlesFr (same order).
 */
final class HygieneArticlesEn
{
    public const ARTICLES = [
        [
            'name' => 'Good practice: choose a strong password',
            'answer' => <<<'HTML'
                <p>A strong password is above all a <strong>long</strong> password: length matters more than complicated characters.</p>
                <h4>The simplest method: the passphrase</h4>
                <p>Put together four or five unrelated words, for example "giraffe-drum-storm-lemon". It is easy to remember and very hard to guess. Avoid famous quotes, song lyrics and anything to do with you (first names, dates of birth, the company name).</p>
                <h4>The rules to remember</h4>
                <ul>
                  <li>at least 14 characters, or four words;</li>
                  <li>a <strong>different password for each service</strong>: if one website is hacked, your other accounts stay protected;</li>
                  <li>never reuse your work password on a personal website.</li>
                </ul>
                <h4>The password manager</h4>
                <p>Nobody can remember dozens of different passwords. A password manager remembers them for you, so you only have one main password to memorise. Ask the support team which one is approved in the company.</p>
                HTML,
        ],
        [
            'name' => 'Good practice: never share your password',
            'answer' => <<<'HTML'
                <p>Your password is personal: it proves that it really is you doing something. Everything done with your account is attributed to you.</p>
                <h4>Nobody needs your password</h4>
                <ul>
                  <li><strong>IT support will never ask you for it</strong>, not by phone, not by email, not by message. Technicians have their own tools to work on your computer.</li>
                  <li>A colleague who needs a file or access should request it for themselves: do not lend them your session.</li>
                  <li>Nor does your manager: an absence is handled with a delegation or a shared folder, not by handing over a password.</li>
                </ul>
                <h4>If someone asks you for it</h4>
                <p>It is probably a scam attempt. Do not reply, and report it to the support team from the portal ("Report an issue").</p>
                <h4>If you gave it out by mistake</h4>
                <p>Change it immediately and let the support team know: reporting it is not a fault, it is the right thing to do.</p>
                HTML,
        ],
        [
            'name' => 'Good practice: lock your session as soon as you leave your desk',
            'answer' => <<<'HTML'
                <p>A computer left unlocked, even for two minutes, lets anyone read your messages, send an email in your name or copy files.</p>
                <h4>The right habit, every time you get up</h4>
                <ul>
                  <li>On Windows: press <strong>Windows + L</strong>.</li>
                  <li>On a Mac: press <strong>Ctrl + Cmd + Q</strong>.</li>
                </ul>
                <p>Your work stays open: you just type your password again when you come back.</p>
                <h4>Also</h4>
                <ul>
                  <li>Do not leave your laptop unattended on a train, in a café or in a meeting room.</li>
                  <li>Put sensitive paper documents away before you leave in the evening.</li>
                  <li>Automatic locking after a few minutes is a safeguard, not a nuisance: do not try to turn it off.</li>
                </ul>
                HTML,
        ],
        [
            'name' => 'Good practice: why you are not an administrator of your computer',
            'answer' => <<<'HTML'
                <p>Your account does not have administrator rights on your computer. This is not a lack of trust: it is the principle of <strong>least privilege</strong>, one of the most effective protections.</p>
                <h4>What it is for</h4>
                <p>Malicious software acts with the rights of the person who opened it. Without administrator rights, it cannot install itself deeply, turn off the antivirus or spread easily. A simple wrong click therefore stays contained.</p>
                <h4>You need to install software or change a setting</h4>
                <ul>
                  <li>Make the request from the portal, in the service catalog ("Request a service").</li>
                  <li>Say which software or setting, and why you need it.</li>
                  <li>The support team installs it for you, or gives you <strong>temporary</strong> rights if this is justified.</li>
                </ul>
                <p>Never install software found on the Internet to get around this limit: that is exactly what attackers are looking for.</p>
                HTML,
        ],
        [
            'name' => 'Good practice: multi-factor authentication',
            'answer' => <<<'HTML'
                <p>Multi-factor authentication adds a second proof to your password: a code sent to your phone, a notification to approve or a security key. Even if someone steals your password, they cannot sign in without this second element.</p>
                <h4>The right habits</h4>
                <ul>
                  <li><strong>Never</strong> approve a sign-in request that you did not start yourself, even if it keeps coming back: it is a sign that someone knows your password. Decline it, then report it to the support team.</li>
                  <li>Never give out a code received by text message or in the app, to anyone, even someone claiming to be from support.</li>
                </ul>
                <h4>You have lost or changed your phone</h4>
                <p>Let the support team know <strong>the same day</strong>, from the portal or by phone. They will deactivate the old device and help you register the new one. Do not wait until you are locked out to report it.</p>
                HTML,
        ],
        [
            'name' => 'Good practice: recognise and report phishing',
            'answer' => <<<'HTML'
                <p>Phishing is a message that imitates a trusted sender to get you to click a link, open an attachment or give away a password. It is the number one way in for attacks.</p>
                <h4>Warning signs</h4>
                <ul>
                  <li>a sense of <strong>urgency</strong>: "your account will be deleted today", "overdue payment";</li>
                  <li>a sender address that looks like the real one, but not quite;</li>
                  <li>a link whose real address (visible by hovering over the link without clicking) does not match the expected website;</li>
                  <li>an unusual request: changing bank details, buying gift cards, giving out a code;</li>
                  <li>an unexpected attachment, even from a known contact.</li>
                </ul>
                <h4>What to do</h4>
                <ul>
                  <li>Do not click and do not open the attachment.</li>
                  <li>If in doubt, contact the sender another way (their usual number, not the one in the message).</li>
                  <li>Report the message to the support team from the portal ("Report an issue").</li>
                </ul>
                <p>Have you already clicked or typed in your password? Let the support team know <strong>immediately</strong>: a few minutes can make all the difference.</p>
                HTML,
        ],
        [
            'name' => 'Good practice: USB sticks and removable media',
            'answer' => <<<'HTML'
                <p>A USB stick can contain malicious software that runs as soon as it is plugged in. It is also easily lost, along with the data on it.</p>
                <h4>The rules to follow</h4>
                <ul>
                  <li><strong>Never</strong> plug in a stick that you found or received without asking for it: hand it to the support team.</li>
                  <li>Only use media supplied or approved by the company.</li>
                  <li>Do not copy company data onto a personal stick.</li>
                  <li>To exchange files, use the company's shared spaces rather than a USB stick.</li>
                </ul>
                <h4>If you lose a stick or a drive</h4>
                <p>Report it to the support team straight away, saying what was on it. If it held personal or confidential data, the company may have to report it within a short deadline.</p>
                HTML,
        ],
        [
            'name' => 'Good practice: do not put off updates',
            'answer' => <<<'HTML'
                <p>Updates fix security flaws that attackers know about and sometimes exploit the very day after they are published. A computer that is not up to date is an open door.</p>
                <h4>What we ask of you</h4>
                <ul>
                  <li>When your computer offers to restart to finish an update, do it as soon as possible, for example at break time or at the end of the day.</li>
                  <li>Do not put off an update several days in a row.</li>
                  <li>Also update your work phone and its apps.</li>
                </ul>
                <h4>If an update causes a problem</h4>
                <p>Has a piece of software stopped working, or is your computer very slow after restarting? Report it to the support team from the portal rather than trying to roll back yourself.</p>
                HTML,
        ],
        [
            'name' => 'Good practice: remote working, public Wi-Fi and VPN',
            'answer' => <<<'HTML'
                <p>Outside the office, your connections go through networks that the company does not control. A few simple rules keep them protected.</p>
                <h4>The VPN</h4>
                <p>The VPN creates an encrypted tunnel between your computer and the company network. <strong>Always connect it</strong> before accessing the company's applications and files.</p>
                <h4>Public Wi-Fi networks</h4>
                <ul>
                  <li>In a station, a hotel or a café, open Wi-Fi can be listened in on or imitated by a fake access point.</li>
                  <li>Use your work phone's hotspot instead.</li>
                  <li>If you do use public Wi-Fi, turn on the VPN before anything else.</li>
                </ul>
                <h4>At home and when travelling</h4>
                <ul>
                  <li>Do not let family or friends use your work computer.</li>
                  <li>Watch out for prying eyes and ears on public transport and in public places: you can ask the support team for a privacy screen filter.</li>
                </ul>
                HTML,
        ],
        [
            'name' => 'Good practice: where to save your documents',
            'answer' => <<<'HTML'
                <p>A document saved only on your computer disappears with it in the event of a breakdown, theft or ransomware. The company's storage spaces, on the other hand, are backed up.</p>
                <h4>Where to save</h4>
                <ul>
                  <li>your work documents: in your personal company space or in your department's shared folders;</li>
                  <li>not on the desktop or in your computer's "Downloads" folder, which are usually not backed up;</li>
                  <li>never on a personal storage service (private online account, personal email).</li>
                </ul>
                <h4>You deleted or overwrote a file by mistake</h4>
                <p>If it was in a backed-up space, the support team can often restore it. Make the request quickly from the portal, giving the file name, its location and the date when it was still correct.</p>
                HTML,
        ],
        [
            'name' => 'Good practice: personal data day to day (GDPR)',
            'answer' => <<<'HTML'
                <p>Personal data is any information that can identify a person: name, address, phone number, customer number, photo, health or payroll data. The GDPR requires it to be protected.</p>
                <h4>Day to day</h4>
                <ul>
                  <li>Only access the data you need for your work.</li>
                  <li>Do not send customer or employee files to a personal address, or through an unapproved service.</li>
                  <li>Check the recipients before sending an email containing personal data.</li>
                  <li>Do not keep copies "just in case": delete what is no longer needed.</li>
                  <li>Print as little as possible and collect your printouts straight away.</li>
                </ul>
                <h4>If you make a mistake</h4>
                <p>An email sent to the wrong person, a file shared too widely, a lost computer: let the support team know immediately. A personal data breach sometimes has to be reported to the supervisory authority within 72 hours.</p>
                HTML,
        ],
        [
            'name' => 'Good practice: what to do in the event of a security incident',
            'answer' => <<<'HTML'
                <p>A security incident is, for example: a computer that is unusually slow or shows unknown windows, files that can no longer be opened, a message demanding a ransom, an email sent in your name, or the loss or theft of a computer or phone.</p>
                <h4>The right reflexes</h4>
                <ul>
                  <li><strong>Let the support team know immediately</strong>, from the portal ("Report an issue") or by phone if you can no longer use your computer.</li>
                  <li>Ransom message or files that cannot be opened: <strong>unplug the network cable and turn off Wi-Fi</strong>, but do not switch off the computer unless the support team tells you to.</li>
                  <li>Never pay a ransom and do not reply to the attackers.</li>
                  <li>Do not try to fix it yourself and do not delete anything: the traces help to understand what happened.</li>
                  <li>Note the time and what you saw; a photo of the screen taken with your phone can help.</li>
                </ul>
                <h4>No shame in reporting</h4>
                <p>Anyone can be caught out. What matters is speed: an incident reported within minutes causes far less damage than one discovered days later.</p>
                HTML,
        ],
    ];
}
