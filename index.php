<?php

////////////////////////////////////////////////////////////////////////////////
//   Sonorizacao v1.1                                                         //
//   Pedido por: Clayton - 04/04/14                                           //
//                                                                            // 
//   Autor: RÔMULO ALMEIDA = Serviços de Alto Nível                           //
//                                                                            //
//   Contato: (24) 98808-5397                                                 //
//   romulosousa17@gmail.com                                                  //
////////////////////////////////////////////////////////////////////////////////


/* DOCUMENTAÇÃO 
 
  Objetivo
  
   Criar um sistema que rode em pararelo ao sistema de Gerenciamento de Consulta utilizando seu banco de dados,
   especificamente a tabela "fila", a fim de este script possa consumi-lo, chamar por voz o paciente que
   estiver na fila, e logo após a chamada, apaga-lo do banco de dados. 
   
  Instalação
  
  1) Instalar o sintetizador de voz raquel:
     1.1) Abrir programa em /Aplicativos/Raquel.exe
     1.2) Clique em Next
     1.3) Aceite os termos ("I accept the terms in the licence agreement") e clique em Next 
     1.4) Clique em Next novamente
     1.5) Clique em Install
     1.6  Clique em Finish
  
  2) Verificar se o PHP é uma variavel de sistema.
     1) Vai em Painel de Controle
     2) Depois em Sistemas e Segurança e em seguida Sistema
     3) Abre configurações avançadas do sistema
     4) Na janela aberta, clique na aba Avançado e depois "Variaveis de Ambiente"
     5) Na janela aberta, em "Em variavels de Sistema" encontre o Item "Path" e clique em Editar
     6) Caso não encontre no campo "Valor da Variavel" um trecho semelhante a este: c:\wamp\bin\php\php5.5.12;, adicione-o manualmente, indicando a localização do seu apache ( no meu caso é o C:/wamp) e em seguida do caminho do php (c:\wamp\bin\php\php5.5.12)
        * Não esqueça do ponto e virgula ";" para separar o caminho do seu directorio dos outros que encontrará neste campo.
  3) Configure o arquivo index.php de acordo com as explicações nele descritos, como parametros de acesso ao banco de dados do Gerenciador de Consultas
  4) Tudo pronto! Execute o programa inicializar.bat para o script se inicializar!   
  
  
  Funcionamento em passos:
  
  [1] Entra em Looping Infinito através da função While(true). É um Sistema que nunca finaliza por si próprio.
  [2] O sistema verifica se o banco de dados está conectado, caso negativo, entra em looping até conseguir a conexão com suscesso.
  [3] O sistema verifica há pacientes na fila, através da função atualizarFila(), que conecta ao banco de dados, e atualiza a variavel $fila com os dados da tabela fila. 
   [4a] Caso Não exista pacientes na fila ($fila): 
        Ele retorna ao looping descrito no passo 1.
  
   [4b] Caso EXISTA pacientes na fila ($fila):
        Chama o paciente na fila através da função toTalk($string) 
         
   
 */




// ## Config #####   
$host = 'localhost';          // Nome do host do Banco de Dados. [String]
$usuario = 'root';            // Nome do usuário do Banco de Dados. [String]
$senha = '75727955';                  // Senha do usuário do Banco de Dados. [String]
$banco_dados = 'bancoteste';  // Nome do Banco de Dados. [String]

$delay_check = 1;    // De quanto em quanto tempo (s) que o sistema irá VERIFICAR por novos pacientes na fila. [Integer]

$max_chamado = 2;   // Máximo de vezes que o sistema irá tentar CHAMAR (executar som) se o MESMO paciente ainda estiver na FILA. [Integer]
$delay_chamada = 2;  // De quanto em quanto tempo (s) que o sistema irá CHAMAR pelo MESMO paciente. [Integer]

$voiceCode = 4;      // Codigo da voz instalada neste computador. [Integer]
$voiceSpeed = -2;    // Velocidade da Voz [-10 até 10]

//################




error_reporting(E_ALL ^ E_DEPRECATED ^ E_WARNING); // A fim de "despoluir" a tela do programa com informações desnecessárias de mensagens do php: warnings e funções depreciadas (que serão removidas do PHP em versões futuras)
set_time_limit(0); // Definir o tempo máximo de execução deste script. Valor 0 significa que nunca será finalizado.

print("Tentando conectar ao Banco de Dados..");
$connect = mysql_connect($host, $usuario, $senha); // Tentativa de conexão ao Banco de Dados do Gerenciamento de Consultas
if (!$connect) { // Se não conseguir conectar, mostrar mensagem de erro abaixo
    die("\n:Falha na conexao com o Banco de Dados!\n\nPossiveis Motivos:\n\n1) Verifique se os dados para a conexao estao corretos no arquivo index.php!\n2)Verifique se o servidor MySql esta Online");
} else { // Caso positivo, mostrar mensagem de conexão bem estabelecida
    print "\n:Conectado com Sucesso ao Banco de dados";
}
$db = mysql_select_db($banco_dados);  // Seleciona o banco de dados que o gerenciador de consultas está operando.

$fila = [];

$tempo_espera = [];
while (true) { /*Passo [1]*/
    sleep($delay_check); // Pausa entre cada ciclo infinito, logo o tempo de espera configuravel na variavel $delay_check, o script seguirá normalmente. Obs: É importante a fim de evitar sobrecarga no processamento deste script.
    if (!mysql_ping()) { /*Passo [2] -> Testa se a conexão com o BD ainda está ativa, caso negativo, tentará reconectar automaticamente*/
        while (!$connect = mysql_connect($host, $usuario, $senha, true)) { // Tenta conectar ao banco de dados que o gerenciador de consultas está operando.
            print("\n:Perda de conexao: Tentando reconectar..");
            sleep(2); // Pausa entre cada tentativa de conexão. Logo após a pausa (sleep), o script continua seu ciclo inicial (while) para verificar se continua sem conexão (!$connect) 
        }
        $db = mysql_select_db($banco_dados); // Seleciona o banco de dados que o gerenciador de consultas está operando.
        print("\n:Conexao estabelecida com Suscesso!");
    }
    print("\n:Verificando pacientes na fila..");

    atualizarFila(); // Atualiza a variavel $fila, com os dados do banco de dados

    if (count($fila) > 0) {  /* Passo [3] - Verifica quantidade (count) de pacientes na fila */
        /* Passo [4b] - EXISTE paciente na fila! */
        for ($i = 0; $i < count($fila); $i++) { // Entra em looping para chamar TODOS os pacientes que estiverem na fila 
            $dados_paciente = $fila[$i]; // Dados do paciente. (Uma array com os valores de cada campo da tabela Fila de 1 paciente ($dados_paciente) )
            for ($i2 = 0; $i2 < $max_chamado; $i2++) { // Looping pra REPETIR a chamada do nome dele, configuravel na variavel $max_chamado.
                
                $frase = $dados_paciente['nome_paciente']." ".$dados_paciente['frase_chamada']." ". $dados_paciente['consultorio']; // Cria a frase que será usada na função abaixo (toTalk) que irá reproduzir a frase ($frase)
                toTalk(utf8_encode($frase)); // Chama funcao que irá executar o som
                atualizarFila(); // Atualiza a variavel $fila, com os dados do banco de dados
                sleep($delay_chamada); // Sleep é a espera (pausa) que o script realiza neste ponto, com o tempo de "pausa" configuravel na variavel $delay_chamada
            }
            mysql_query("DELETE FROM fila WHERE id = $dados_paciente[id]"); // TIRA (deleta) o paciente da fila
        }
    }
    atualizarFila(); // Atualiza a variavel $fila, com os dados do banco de dados
    
    // Retorna ao inicio do looping infinito
}


/* toTalk()
 * 
 * Esta função é chamada periodicamente a fim de verificar se a fila contém algum paciente a ser chamado, caso positivo, joga todas as informações
 * deste paciente para a variavél $fila, que será usada para formar a string para enviar para a função toTalk().
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
 * O objetivo desta função é pegar uma string através da variavel $text e transforma-la em audio, e após isso executar o recém criado .mp3 atraves do programa sounder.exe
 * 
 * Observações:
 * $toPrint = Uma variavel puramente informativa, para que mostre informações na "tela preta" do prompt de comando do Windows
 *  */

function toTalk($text) { // Funcao que faz criar (sapi2wav.exe) e executar (sounder.exe) a variavel ($text) através da Linha de Comando (exec)
    global $voiceCode, $voiceSpeed;

    $text = "<rate speed='$voiceSpeed'>$text</rate>"; // Este é um texto que é entendido pelo sintetizador de voz. Percebe-se o uso de tags XML para o sintetizar identificar nossa string ($text) e a velocidade que queremos que seja lido ($voiceSpeed)

    $toPrint = '';
    $toPrint.= exec(utf8_decode("sapi2wav talk.wav $voiceCode -t \"$text\"")); // Cria o .wav, chamando o programa sapi2wave através da linha de comando do Windows, para gerar o .wav
    $toPrint.= exec("sounder /stop");   // Para qualquer execução de som.
    $toPrint.= exec("sounder talk.wav"); // Executa o .wav criado acima através chamada do programa souder na linha de comando do Windows
    print("\n:Enviada linha de comando. Saida: $toPrint");
    
}

?>
