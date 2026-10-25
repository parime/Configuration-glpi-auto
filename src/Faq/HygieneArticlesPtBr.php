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
 * Issue #321: end-user security good-practice articles, Brazilian Portuguese translation of
 * HygieneArticlesFr (same order).
 */
final class HygieneArticlesPtBr
{
    public const ARTICLES = [
        [
            'name' => 'Boas práticas: escolher uma senha forte',
            'answer' => <<<'HTML'
                <p>Uma senha forte é, antes de tudo, uma senha <strong>longa</strong>: o tamanho conta mais do que os caracteres complicados.</p>
                <h4>O método mais simples: a frase-senha</h4>
                <p>Junte quatro ou cinco palavras sem relação entre si, por exemplo "girafa-tambor-tempestade-limão". É fácil de lembrar e muito difícil de adivinhar. Evite frases famosas, letras de música e tudo o que tenha a ver com você (nomes, datas de nascimento, nome da empresa).</p>
                <h4>As regras para lembrar</h4>
                <ul>
                  <li>pelo menos 14 caracteres, ou quatro palavras;</li>
                  <li>uma senha <strong>diferente para cada serviço</strong>: se um site for invadido, suas outras contas continuam protegidas;</li>
                  <li>nunca reutilize sua senha do trabalho em um site pessoal.</li>
                </ul>
                <h4>O gerenciador de senhas</h4>
                <p>Ninguém consegue lembrar dezenas de senhas diferentes. Um gerenciador de senhas guarda todas para você, e você só precisa memorizar uma única senha principal. Pergunte ao suporte qual gerenciador é autorizado na empresa.</p>
                HTML,
        ],
        [
            'name' => 'Boas práticas: nunca compartilhar sua senha',
            'answer' => <<<'HTML'
                <p>Sua senha é pessoal: ela prova que é você mesmo quem está agindo. Tudo o que é feito com a sua conta é atribuído a você.</p>
                <h4>Ninguém precisa da sua senha</h4>
                <ul>
                  <li><strong>O suporte de TI nunca vai pedir sua senha</strong>, nem por telefone, nem por e-mail, nem por mensagem. Os técnicos têm suas próprias ferramentas para trabalhar.</li>
                  <li>Um colega que precisa de um arquivo ou de um acesso deve solicitá-lo para ele mesmo: não empreste a sua sessão.</li>
                  <li>Seu gestor também não: uma ausência se resolve com uma delegação ou o compartilhamento de uma pasta, não passando a senha.</li>
                </ul>
                <h4>Se alguém pedir sua senha</h4>
                <p>Provavelmente é uma tentativa de golpe. Não responda e avise o suporte pelo portal ("Reportar um incidente").</p>
                <h4>Se você passou a senha por engano</h4>
                <p>Troque-a imediatamente e avise o suporte: avisar não é um erro, é a atitude certa.</p>
                HTML,
        ],
        [
            'name' => 'Boas práticas: bloquear a sessão sempre que sair do computador',
            'answer' => <<<'HTML'
                <p>Um computador deixado desbloqueado, mesmo por dois minutos, permite que qualquer pessoa leia suas mensagens, envie um e-mail em seu nome ou copie arquivos.</p>
                <h4>A atitude certa, sempre que você se levantar</h4>
                <ul>
                  <li>No Windows: pressione as teclas <strong>Windows + L</strong>.</li>
                  <li>No Mac: pressione <strong>Ctrl + Cmd + Q</strong>.</li>
                </ul>
                <p>Seu trabalho continua aberto: basta digitar a senha novamente quando voltar.</p>
                <h4>E também</h4>
                <ul>
                  <li>Não deixe seu notebook sem supervisão em um trem, em um café ou em uma sala de reunião.</li>
                  <li>Guarde os documentos em papel confidenciais antes de ir embora no fim do dia.</li>
                  <li>O bloqueio automático depois de alguns minutos é uma proteção, não um incômodo: não tente desativá-lo.</li>
                </ul>
                HTML,
        ],
        [
            'name' => 'Boas práticas: por que você não é administrador do seu computador',
            'answer' => <<<'HTML'
                <p>Sua conta não tem direitos de administrador no seu computador. Não é falta de confiança: é o princípio do <strong>menor privilégio</strong>, uma das proteções mais eficazes.</p>
                <h4>Para que serve</h4>
                <p>Um programa malicioso age com os direitos da pessoa que o abriu. Sem direitos de administrador, ele não consegue se instalar a fundo, nem desativar o antivírus, nem se espalhar com facilidade. Assim, um simples clique errado tem consequências limitadas.</p>
                <h4>Você precisa instalar um programa ou mudar uma configuração</h4>
                <ul>
                  <li>Faça o pedido pelo portal, no catálogo de serviços ("Solicitar um serviço").</li>
                  <li>Informe o programa ou a configuração, e por que você precisa dele.</li>
                  <li>O suporte instala para você, ou concede um direito <strong>temporário</strong> se for justificado.</li>
                </ul>
                <p>Nunca instale um programa encontrado na internet para contornar esse limite: é exatamente isso que os atacantes procuram.</p>
                HTML,
        ],
        [
            'name' => 'Boas práticas: a autenticação multifator',
            'answer' => <<<'HTML'
                <p>A autenticação multifator acrescenta uma segunda prova à sua senha: um código recebido no celular, uma notificação para confirmar ou uma chave de segurança. Mesmo que alguém roube sua senha, não consegue entrar sem esse segundo elemento.</p>
                <h4>As atitudes certas</h4>
                <ul>
                  <li><strong>Nunca</strong> aprove um pedido de acesso que não foi você que fez, mesmo que ele se repita: é sinal de que alguém conhece sua senha. Recuse e depois avise o suporte.</li>
                  <li>Nunca passe a ninguém um código recebido por SMS ou pelo aplicativo, nem mesmo a alguém que diz ser do suporte.</li>
                </ul>
                <h4>Você perdeu ou trocou de celular</h4>
                <p>Avise o suporte <strong>no mesmo dia</strong>, pelo portal ou por telefone. Ele vai desativar o aparelho antigo e ajudar você a cadastrar o novo. Não espere ficar bloqueado para avisar.</p>
                HTML,
        ],
        [
            'name' => 'Boas práticas: reconhecer um phishing e reportá-lo',
            'answer' => <<<'HTML'
                <p>O phishing é uma mensagem que imita um remetente de confiança para fazer você clicar em um link, abrir um anexo ou informar uma senha. É a principal porta de entrada dos ataques.</p>
                <h4>Os sinais que devem deixar você alerta</h4>
                <ul>
                  <li>uma sensação de <strong>urgência</strong>: "sua conta será excluída hoje", "pagamento atrasado";</li>
                  <li>um endereço de remetente parecido com o verdadeiro, mas não exatamente igual;</li>
                  <li>um link cujo endereço real (visível ao passar o mouse sobre o link sem clicar) não corresponde ao site esperado;</li>
                  <li>um pedido incomum: alterar dados bancários, comprar cartões-presente, informar um código;</li>
                  <li>um anexo inesperado, mesmo vindo de um contato conhecido.</li>
                </ul>
                <h4>O que fazer</h4>
                <ul>
                  <li>Não clique e não abra o anexo.</li>
                  <li>Em caso de dúvida, entre em contato com o remetente por outro meio (o número de sempre, não o da mensagem).</li>
                  <li>Reporte a mensagem ao suporte pelo portal ("Reportar um incidente").</li>
                </ul>
                <p>Você já clicou ou digitou sua senha? Avise o suporte <strong>imediatamente</strong>: alguns minutos podem fazer toda a diferença.</p>
                HTML,
        ],
        [
            'name' => 'Boas práticas: pen drives e mídias removíveis',
            'answer' => <<<'HTML'
                <p>Um pen drive pode conter um programa malicioso que é executado assim que ele é conectado. Ele também se perde com facilidade, junto com os dados que contém.</p>
                <h4>As regras a seguir</h4>
                <ul>
                  <li><strong>Nunca</strong> conecte um pen drive encontrado ou recebido sem ter pedido: entregue-o ao suporte.</li>
                  <li>Use apenas as mídias fornecidas ou autorizadas pela empresa.</li>
                  <li>Não copie dados da empresa para um pen drive pessoal.</li>
                  <li>Para trocar arquivos, prefira os espaços de compartilhamento da empresa ao pen drive.</li>
                </ul>
                <h4>Se você perder um pen drive ou um disco</h4>
                <p>Avise o suporte sem demora, informando o que havia nele. Se ele continha dados pessoais ou confidenciais, a empresa pode ter a obrigação de comunicar o ocorrido em prazos curtos.</p>
                HTML,
        ],
        [
            'name' => 'Boas práticas: não adiar as atualizações',
            'answer' => <<<'HTML'
                <p>As atualizações corrigem falhas de segurança que os atacantes conhecem e, às vezes, exploram já no dia seguinte à sua publicação. Um computador desatualizado é uma porta aberta.</p>
                <h4>O que pedimos a você</h4>
                <ul>
                  <li>Quando o computador pedir para reiniciar para concluir uma atualização, faça isso o quanto antes, por exemplo no intervalo ou no fim do dia.</li>
                  <li>Não adie uma atualização vários dias seguidos.</li>
                  <li>Atualize também seu celular corporativo e os aplicativos dele.</li>
                </ul>
                <h4>Se uma atualização causar problemas</h4>
                <p>Um programa parou de funcionar, o computador ficou muito lento depois de reiniciar? Avise o suporte pelo portal em vez de tentar desfazer a atualização por conta própria.</p>
                HTML,
        ],
        [
            'name' => 'Boas práticas: home office, Wi-Fi público e VPN',
            'answer' => <<<'HTML'
                <p>Fora da empresa, suas comunicações passam por redes que a empresa não controla. Algumas regras simples as protegem.</p>
                <h4>A VPN</h4>
                <p>A VPN cria um túnel criptografado entre o seu computador e a rede da empresa. <strong>Conecte-a sempre</strong> antes de acessar os aplicativos e os arquivos da empresa.</p>
                <h4>As redes Wi-Fi públicas</h4>
                <ul>
                  <li>Em uma estação, um hotel ou um café, um Wi-Fi aberto pode ser espionado ou imitado por um ponto de acesso falso.</li>
                  <li>Prefira o roteamento (compartilhamento de internet) do seu celular corporativo.</li>
                  <li>Se você usar um Wi-Fi público, ative a VPN antes de qualquer outra coisa.</li>
                </ul>
                <h4>Em casa e em viagem</h4>
                <ul>
                  <li>Não deixe familiares ou amigos usarem seu computador de trabalho.</li>
                  <li>Cuidado com olhares e ouvidos curiosos no transporte e em lugares públicos: você pode pedir ao suporte um filtro de privacidade para a tela.</li>
                </ul>
                HTML,
        ],
        [
            'name' => 'Boas práticas: onde salvar seus documentos',
            'answer' => <<<'HTML'
                <p>Um documento salvo apenas no seu computador desaparece junto com ele em caso de pane, roubo ou ransomware. Já os espaços da empresa têm backup.</p>
                <h4>Onde salvar</h4>
                <ul>
                  <li>seus documentos de trabalho: no seu espaço pessoal da empresa ou nas pastas compartilhadas do seu setor;</li>
                  <li>não na área de trabalho nem na pasta "Downloads" do seu computador, que geralmente não têm backup;</li>
                  <li>nunca em um serviço de armazenamento pessoal (conta on-line particular, e-mail pessoal).</li>
                </ul>
                <h4>Você excluiu ou sobrescreveu um arquivo por engano</h4>
                <p>Se ele estava em um espaço com backup, o suporte muitas vezes consegue restaurá-lo. Faça o pedido rapidamente pelo portal, informando o nome do arquivo, onde ele estava e a data em que ele ainda estava correto.</p>
                HTML,
        ],
        [
            'name' => 'Boas práticas: dados pessoais no dia a dia (LGPD/GDPR)',
            'answer' => <<<'HTML'
                <p>Dados pessoais são todas as informações que permitem identificar uma pessoa: nome, endereço, telefone, número de cliente, foto, dados de saúde ou de folha de pagamento. A LGPD/GDPR exige que eles sejam protegidos.</p>
                <h4>No dia a dia</h4>
                <ul>
                  <li>Acesse apenas os dados de que você precisa para o seu trabalho.</li>
                  <li>Não envie arquivos de clientes ou de funcionários para um endereço pessoal, nem por um serviço não autorizado.</li>
                  <li>Confira os destinatários antes de enviar um e-mail com dados pessoais.</li>
                  <li>Não guarde cópias "por via das dúvidas": exclua o que não é mais útil.</li>
                  <li>Imprima o mínimo possível e retire suas impressões na hora.</li>
                </ul>
                <h4>Em caso de erro</h4>
                <p>Um e-mail enviado ao destinatário errado, um arquivo compartilhado com gente demais, um computador perdido: avise o suporte imediatamente. Um vazamento de dados pessoais às vezes precisa ser comunicado à autoridade de proteção de dados em até 72 horas.</p>
                HTML,
        ],
        [
            'name' => 'Boas práticas: o que fazer em caso de incidente de segurança',
            'answer' => <<<'HTML'
                <p>Um incidente de segurança é, por exemplo: um computador anormalmente lento ou que mostra janelas desconhecidas, arquivos que ficaram ilegíveis, uma mensagem exigindo um resgate, um e-mail enviado em seu nome, a perda ou o roubo de um computador ou de um celular.</p>
                <h4>As atitudes certas</h4>
                <ul>
                  <li><strong>Avise o suporte imediatamente</strong>, pelo portal ("Reportar um incidente") ou por telefone se você não conseguir mais usar o computador.</li>
                  <li>Mensagem de resgate ou arquivos ilegíveis: <strong>desconecte o cabo de rede e desligue o Wi-Fi</strong>, mas não desligue o computador sem orientação do suporte.</li>
                  <li>Nunca pague um resgate e não responda aos atacantes.</li>
                  <li>Não tente consertar por conta própria e não apague nada: os rastros ajudam a entender o que aconteceu.</li>
                  <li>Anote o horário e o que você viu; uma foto da tela tirada com o celular pode ajudar.</li>
                </ul>
                <h4>Não tenha vergonha de avisar</h4>
                <p>Qualquer pessoa pode cair em uma armadilha. O que importa é a rapidez: um incidente avisado em poucos minutos causa muito menos estragos do que um incidente descoberto dias depois.</p>
                HTML,
        ],
    ];
}
