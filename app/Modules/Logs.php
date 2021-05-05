<?php

namespace WpRloutHtml\Modules;

use Serasa\Manager\Helper\AdminListTable;
// use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\Csv;

/**
 * IdentificLogs Classe responsável por gerenciar os logs Identific
 */
class Logs {

    static $wpdb;
    static $table;
    static $columns = array(
        'id' => array(
            'label' => 'ID',
            'type' => 'INT',
            'attrs' => 'NOT NULL AUTO_INCREMENT',
        ),
        'date_time' => array(
            'label' => 'Data/hora',
            'type' => 'DATETIME',
            'attrs' => 'NOT NULL',
        ),
        'file_static' => array(
            'label' => 'Url static',
            'type' => 'TEXT',
            'attrs' => '',
        ),
        'error_log' => array(
            'label' => 'static enviado',
            'type' => 'TEXT',
            'attrs' => '',
        ),
    );
    static $listTableColumnsKeys = ['date_time', 'file_static', 'error_log'];

    /**
     * init Inicializa variáveis caso necessário
     *
     * @return void
     */
    private static function init() {
        if(!empty(self::$wpdb))
            return;

        global $wpdb;
        self::$wpdb = $wpdb;

		self::$table = self::$wpdb->prefix . "static_output_errors_log";
	}

	public static function enableLogs() {
		if(FALSE === get_option('static_output_errors_log_enabled') && FALSE === update_option('static_output_errors_log_enabled', FALSE))
			add_option('static_output_errors_log_enabled', true);
		else
			update_option('static_output_errors_log_enabled', !get_option('static_output_errors_log_enabled'));
	}

    /**
     * createTable Cria a tabela de registro de logs
     *
     * @return bool True para tabela criada com sucesso, false para erro na criação
     */
    public static function createTable(): bool {
        self::init();
        $columns = "";
        foreach(self::$columns as $key => $value)
            $columns .= "{$key} {$value['type']} {$value['attrs']},";

        return self::$wpdb->query("CREATE TABLE IF NOT EXISTS " . self::$table . " ({$columns} PRIMARY KEY (id));");
    }

    /**
     * alterTable Altera a tabela para condizer com as colunas definidas em $columns
     *
     * @return bool True para tabela alterada com sucesso, false para erro na alteração
     */
    public static function alterTable(): bool {
        self::init();
        $columnsData = self::$wpdb->get_results("SHOW COLUMNS FROM " . self::$table);
        $columns = "";

        foreach(self::$columns as $key => $value):
            $exists = false;
            foreach($columnsData as $columnValue):
                if($key == $columnValue->Field):
                    $exists = true;
                    break;
                endif;
            endforeach;

            if(empty($exists))
                $columns .= " ADD COLUMN {$key} {$value['type']} {$value['attrs']},";
        endforeach;

        return self::$wpdb->query("ALTER TABLE " . self::$table . substr($columns, 0, -1));
    }

    /**
     * adminPage Cria a página na dashboard de admin
     *
     * @return void
     */
    public static function adminPage() {
		self::init();
        include(dirname(__FILE__) . "/../../View/static_outputLogs.php");
    }

    /**
     * tableExists Verifica se a tabela de registro de logs existe
     *
     * @return string|null String para o resultado da query, null para falha
     */
    public static function tableExists() {
        self::init();
        return self::$wpdb->get_var(self::$wpdb->prepare("SHOW TABLES LIKE '%s'", self::$table)) === self::$table;
    }

    /**
     * tableUpdated Verifica se a tabela de registro de logs está atualizada, com todas colunas definas em $columns
     *
     * @return string|null String para o resultado da query, null para falha
     */
    public static function tableUpdated() {
        self::init();
        return !self::tableExists() ? 0 : count(self::$wpdb->get_results("SHOW COLUMNS FROM " . self::$table)) == count(self::$columns);
    }

    /**
     * insert Insere dados na tabela de registro de logs
     *
     * @param  array $data Array de dados a serem inseridos
     * @return int|string Int para o id da linha inserida, string para mensagem de erro
     */
    public static function insert($data) {
        self::init();
        self::$wpdb->insert(self::$table, $data);

        return !empty(self::$wpdb->insert_id) ? self::$wpdb->insert_id : self::$wpdb->last_error;
    }

    /**
     * listTable Exibe a tabela de listagem dos registros de logs
     *
     * @return void
     */
    public static function listTable() {
        self::init();
        $columns = self::getListTableColumns();
        $columnsData = array();

        foreach($columns as $key => $value)
            $columnsData[$key] = $value['label'];

        new AdminListTable(
            self::$table,
            array(
                'singular' => 'Log',
                'plural' => 'Logs',
            ),
            array(
                'columns' => $columnsData,
                'orderby' => 'date_time',
                'order' => 'desc',
                'search' => array(
                    'date_time',
                ),
                'sortable' => array(
                    'date_time' => 'date_time',
                ),
                'per_page' => 40,
            )
        );
    }

    /**
     * getListTableColumns Filtra as colunas que serão exibidas a tabela de listagem
     *
     * @return array Colunas filtradas que serão exibidas
     */
    private static function getListTableColumns(): array {
        $keys = self::$listTableColumnsKeys;

        return array_filter(
            self::$columns,
            function ($key) use ($keys) {
                return in_array($key, $keys);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * export Exporta os registros de logs gravados
     *
     * @return void
     */
    public static function export() {
        self::init();
        $columns = self::getListTableColumns();
        $columns = array_map(function($object) {
            return $object['label'];
        }, $columns);
        $columns = array_values($columns);

        $columnsSql = implode(', ', self::$listTableColumnsKeys);
        $data = self::$wpdb->get_results(
            self::$wpdb->prepare(
                "SELECT {$columnsSql}
                FROM " . self::$table . "
                WHERE date(date_time) >= %s AND date(date_time) <= %s
                ORDER BY date_time DESC",
                $_POST['date_from'],
                $_POST['date_to']
            )
        );

        foreach($data as $item)
            $item->product = strlen($item->cpf_cnpj) == 11 ? 'e-CPF' : (strlen($item->cpf_cnpj) == 14 ? 'e-CNPJ' : '');

        $data = json_decode(json_encode($data), true);
        $data = array_map(function($object) {
            return array_values($object);
        }, $data);

        array_unshift($data, $columns);

        $filename = "identific-logs.csv";
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(
            $data,   // The data to set
            NULL,    // Array values with this value will not be set
            'A1'     // Top left coordinate of the worksheet range where we want to set these values (default is A1)
        );

        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(';');
        $writer->setUseBOM(true);
        $writer->save($_SERVER['DOCUMENT_ROOT'] . "/" . $filename);

        header("Location: " . $_SERVER['HTTP_ORIGIN'] . "/" . $filename);
    }

}