<?php

////////////////////////////////////////////////////////////////////////////////
//   Sonorizacao v1.1                                                         //
//   Pedido por: Clayton - 04/04/14                                           //
//                                                                            // 
//   Autor: R�MULO ALMEIDA = Servi�os de Alto N�vel                           //
//                                                                            //
//   Contato: (24) 98808-5397                                                 //
//   romulosousa17@gmail.com                                                  //
////////////////////////////////////////////////////////////////////////////////


/* DOCUMENTA��O 
 
  Objetivo
  
   Criar um sistema que rode em pararelo ao sistema de Gerenciamento de Consulta utilizando seu banco de dados,
   especificamente a tabela "fila", a fim de este script possa consumi-lo, chamar por voz o paciente que
   estiver na fila, e logo ap�s a chamada, apaga-lo do banco de dados. 
   
  Instala��o
  
  1) Instalar o sintetizador de voz raquel:
     1.1) Abrir programa em /Aplicativos/Raquel.exe
     1.2) Clique em Next
     1.3) Aceite os termos ("I accept the terms in the licence agreement") e clique em Next 
     1.4) Clique em Next novamente
     1.5) Clique em Install
     1.6  Clique em Finish
  
  2) Verificar se o PHP � uma variavel de sistema.
     1) Vai em Painel de Controle
     2) Depois em Sistemas e Seguran�a e em seguida Sistema
     3) Abre configura��es avan�adas do sistema
     4) Na janela aberta, clique na aba Avan�ado e depois "Variaveis de Ambiente"
     5) Na janela aberta, em "Em variavels de Sistema" encontre o Item "Path" e clique em Editar
     6) Caso n�o encontre no campo "Valor da Variavel" um trecho semelhante a este: c:\wamp\bin\php\php5.5.12;, adicione-o manualmente, indicando a localiza��o do seu apache ( no meu caso � o C:/wamp) e em seguida do caminho do php (c:\wamp\bin\php\php5.5.12)
        * N�o esque�a do ponto e virgula ";" para separar o caminho do seu directorio dos outros que encontrar� neste campo.
  3) Configure o arquivo index.php de acordo com as explica��es nele descritos, como parametros de acesso ao banco de dados do Gerenciador de Consultas
  4) Tudo pronto! Execute o programa inicializar.bat para o script se inicializar!   
  
  
  Funcionamento em passos:
  
  [1] Entra em Looping Infinito atrav�s da fun��o While(true). � um Sistema que nunca finaliza por si pr�prio.
  [2] O sistema verifica se o banco de dados est� conectado, caso negativo, entra em looping at� conseguir a conex�o com suscesso.
  [3] O sistema verifica h� pacientes na fila, atrav�s da fun��o atualizarFila(), que conecta ao banco de dados, e atualiza a variavel $fila com os dados da tabela fila. 
   [4a] Caso N�o exista pacientes na fila ($fila): 
        Ele retorna ao looping descrito no passo 1.
  
   [4b] Caso EXISTA pacientes na fila ($fila):
        Chama o paciente na fila atrav�s da fun��o toTalk($string) 
         
   
 */




// ## Config #####   
$host = 'localhost';          // Nome do host do Banco de Dados. [String]
$usuario = 'root';            // Nome do usu�rio do Banco de Dados. [String]
$senha = '75727955';                  // Senha do usu�rio do Banco de Dados. [String]
$banco_dados = 'bancoteste';  // Nome do Banco de Dados. [String]

$delay_check = 1;    // De quanto em quanto tempo (s) que o sistema ir� VERIFICAR por novos pacientes na fila. [Integer]

$max_chamado = 2;   // M�ximo de vezes que o sistema ir� tentar CHAMAR (executar som) se o MESMO paciente ainda estiver na FILA. [Integer]
$delay_chamada = 2;  // De quanto em quanto tempo (s) que o sistema ir� CHAMAR pelo MESMO paciente. [Integer]

$voiceCode = 4;      // Codigo da voz instalada neste computador. [Integer]
$voiceSpeed = -2;    // Velocidade da Voz [-10 at� 10]

//################




error_reporting(E_ALL ^ E_DEPRECATED ^ E_WARNING); // A fim de "despoluir" a tela do programa com informa��es desnecess�rias de mensagens do php: warnings e fun��es depreciadas (que ser�o removidas do PHP em vers�es futuras)
set_time_limit(0); // Definir o tempo m�ximo de execu��o deste script. Valor 0 significa que nunca ser� finalizado.

print("Tentando conectar ao Banco de Dados..");
$connect = mysql_connect($host, $usuario, $senha); // Tentativa de conex�o ao Banco de Dados do Gerenciamento de Consultas
if (!$connect) { // Se n�o conseguir conectar, mostrar mensagem de erro abaixo
    die("\n:Falha na conexao com o Banco de Dados!\n\nPossiveis Motivos:\n\n1) Verifique se os dados para a conexao estao corretos no arquivo index.php!\n2)Verifique se o servidor MySql esta Online");
} else { // Caso positivo, mostrar mensagem de conex�o bem estabelecida
    print "\n:Conectado com Sucesso ao Banco de dados";
}
$db = mysql_select_db($banco_dados);  // Seleciona o banco de dados que o gerenciador de consultas est� operando.

$fila = [];

$tempo_espera = [];
while (true) { /*Passo [1]*/
    sleep($delay_check); // Pausa entre cada ciclo infinito, logo o tempo de espera configuravel na variavel $delay_check, o script seguir� normalmente. Obs: � importante a fim de evitar sobrecarga no processamento deste script.
    if (!mysql_ping()) { /*Passo [2] -> Testa se a conex�o com o BD ainda est� ativa, caso negativo, tentar� reconectar automaticamente*/
        while (!$connect = mysql_connect($host, $usuario, $senha, true)) { // Tenta conectar ao banco de dados que o gerenciador de consultas est� operando.
            print("\n:Perda de conexao: Tentando reconectar..");
            sleep(2); // Pausa entre cada tentativa de conex�o. Logo ap�s a pausa (sleep), o script continua seu ciclo inicial (while) para verificar se continua sem conex�o (!$connect) 
        }
        $db = mysql_select_db($banco_dados); // Seleciona o banco de dados que o gerenciador de consultas est� operando.
        print("\n:Conexao estabelecida com Suscesso!");
    }
    print("\n:Verificando pacientes na fila..");

    atualizarFila(); // Atualiza a variavel $fila, com os dados do banco de dados

    if (count($fila) > 0) {  /* Passo [3] - Verifica quantidade (count) de pacientes na fila */
        /* Passo [4b] - EXISTE paciente na fila! */
        for ($i = 0; $i < count($fila); $i++) { // Entra em looping para chamar TODOS os pacientes que estiverem na fila 
            $dados_paciente = $fila[$i]; // Dados do paciente. (Uma array com os valores de cada campo da tabela Fila de 1 paciente ($dados_paciente) )
            for ($i2 = 0; $i2 < $max_chamado; $i2++) { // Looping pra REPETIR a chamada do nome dele, configuravel na variavel $max_chamado.
                
                $frase = $dados_paciente['nome_paciente']." ".$dados_paciente['frase_chamada']." ". $dados_paciente['consultorio']; // Cria a frase que ser� usada na fun��o abaixo (toTalk) que ir� reproduzir a frase ($frase)
                toTalk(utf8_encode($frase)); // Chama funcao que ir� executar o som
                atualizarFila(); // Atualiza a variavel $fila, com os dados do banco de dados
                sleep($delay_chamada); // Sleep � a espera (pausa) que o script realiza neste ponto, com o tempo de "pausa" configuravel na variavel $delay_chamada
            }
            mysql_query("DELETE FROM fila WHERE id = $dados_paciente[id]"); // TIRA (deleta) o paciente da fila
        }
    }
    atualizarFila(); // Atualiza a variavel $fila, com os dados do banco de dados
    
    // Retorna ao inicio do looping infinito
}


/* toTalk()
 * 
 * Esta fun��o � chamada periodicamente a fim de verificar se a fila cont�m algum paciente a ser chamado, caso positivo, joga todas as informa��es
 * deste paciente para a variav�l $fila, que ser� usada para formar a string para enviar para a fun��o toTalk().
 * 
 *  */

function atualizarFila() {
    global $fila, $max_chamado, $connect;

    $SQL = "SELECT * FROM fila"; // Consulta a tabela de fila
    $consulta = mysql_query($SQL, $connect);
    echo mysql_error();
    $fila = [];
        while ($linha = mysql_fetch_array($consulta)) { // Pega o resultado da consulta e coloca na variavel global $fila
            $fila[] = $linha;
        }
    
}

/* toTalk()
 * 
 * O objetivo desta fun��o � pegar uma string atrav�s da variavel $text e transforma-la em audio, e ap�s isso executar o rec�m criado .mp3 atraves do programa sounder.exe
 * 
 * Observa��es:
 * $toPrint = Uma variavel puramente informativa, para que mostre informa��es na "tela preta" do prompt de comando do Windows
 *  */

function toTalk($text) { // Funcao que faz criar (sapi2wav.exe) e executar (sounder.exe) a variavel ($text) atrav�s da Linha de Comando (exec)
    global $voiceCode, $voiceSpeed;

    $text = "<rate speed='$voiceSpeed'>$text</rate>"; // Este � um texto que � entendido pelo sintetizador de voz. Percebe-se o uso de tags XML para o sintetizar identificar nossa string ($text) e a velocidade que queremos que seja lido ($voiceSpeed)

    $toPrint = '';
    $toPrint.= exec(utf8_decode("sapi2wav talk.wav $voiceCode -t \"$text\"")); // Cria o .wav, chamando o programa sapi2wave atrav�s da linha de comando do Windows, para gerar o .wav
    $toPrint.= exec("sounder /stop");   // Para qualquer execu��o de som.
    $toPrint.= exec("sounder talk.wav"); // Executa o .wav criado acima atrav�s chamada do programa souder na linha de comando do Windows
    print("\n:Enviada linha de comando. Saida: $toPrint");
    
}

?>
