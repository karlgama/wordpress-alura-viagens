<?php
/**
 * As configurações básicas do WordPress
 *
 * O script de criação wp-config.php usa esse arquivo durante a instalação.
 * Você não precisa usar o site, você pode copiar este arquivo
 * para "wp-config.php" e preencher os valores.
 *
 * Este arquivo contém as seguintes configurações:
 *
 * * Configurações do banco de dados
 * * Chaves secretas
 * * Prefixo do banco de dados
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */
define('FS_METHOD', 'direct');

// ** Configurações do banco de dados - Você pode pegar estas informações com o serviço de hospedagem ** //
/** O nome do banco de dados do WordPress */
define( 'DB_NAME', 'alura' );

/** Usuário do banco de dados MySQL */
define( 'DB_USER', 'root' );

/** Senha do banco de dados MySQL */
define( 'DB_PASSWORD', '' );

/** Nome do host do MySQL */
define( 'DB_HOST', 'localhost' );

/** Charset do banco de dados a ser usado na criação das tabelas. */
define( 'DB_CHARSET', 'utf8mb4' );

/** O tipo de Collate do banco de dados. Não altere isso se tiver dúvidas. */
define( 'DB_COLLATE', '' );

/**#@+
 * Chaves únicas de autenticação e salts.
 *
 * Altere cada chave para um frase única!
 * Você pode gerá-las
 * usando o {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org
 * secret-key service}
 * Você pode alterá-las a qualquer momento para invalidar quaisquer
 * cookies existentes. Isto irá forçar todos os
 * usuários a fazerem login novamente.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '0q_^&ksxc1sY)o>oA?[@wE6;?2QB6xu1RWB3-CqdoE{IeS?AM8{rTKrC^q v>[Jq' );
define( 'SECURE_AUTH_KEY',  '>|Et)(1;b.#<)?SrvG%9`3q$^a7lDDhCSa[F8^H;gxeZW;-D3-{3(_Hx](R/&vti' );
define( 'LOGGED_IN_KEY',    ')VU YcAke-VuN-yjS71{ lXu4|{|uSXa^e`m^qD5IK&+`#76K|$YS$@<1U/sorOO' );
define( 'NONCE_KEY',        '#;;ibkCR;tC;+oQa1,!UTBjV;9x%W35@5]I7Ux1hUn*hne^_nJX4poDi}@M5*nOH' );
define( 'AUTH_SALT',        '~.7Cai!vJ8e+]NpgTAG|F9]?%^a3zc[)lxNH]fR>6t0gHhz&;5R*z9*/!3Rz:A4a' );
define( 'SECURE_AUTH_SALT', '~^pwVW:8?{TLS!F(5)|.#$M_s`<hIt.Ca7J]]8nIM@@E F+[pbRR[{@%GK{&|zzW' );
define( 'LOGGED_IN_SALT',   '_ 7iF4<,vA_<hJhpEW[V`0HMSlOy:OZ)U@a2{BrlrmLZcub5DOE_s[Z4#MbJt1Pn' );
define( 'NONCE_SALT',       '&8D67]cM&0VYQd7D$tW6[@),YcW&>QNd@OMa($]Y9|G9x*EP8XEC2WJO>3>fKVbI' );

/**#@-*/

/**
 * Prefixo da tabela do banco de dados do WordPress.
 *
 * Você pode ter várias instalações em um único banco de dados se você der
 * um prefixo único para cada um. Somente números, letras e sublinhados!
 */
$table_prefix = 'wp_';

/**
 * Para desenvolvedores: Modo de debug do WordPress.
 *
 * Altere isto para true para ativar a exibição de avisos
 * durante o desenvolvimento. É altamente recomendável que os
 * desenvolvedores de plugins e temas usem o WP_DEBUG
 * em seus ambientes de desenvolvimento.
 *
 * Para informações sobre outras constantes que podem ser utilizadas
 * para depuração, visite o Codex.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Adicione valores personalizados entre esta linha até "Isto é tudo". */



/* Isto é tudo, pode parar de editar! :) */

/** Caminho absoluto para o diretório WordPress. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Configura as variáveis e arquivos do WordPress. */
require_once ABSPATH . 'wp-settings.php';
