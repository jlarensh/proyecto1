<?php

namespace UserFrosting;
use ZipArchive;
/**
 * GroupController Class
 *
 * Controller class for /groups/* URLs.  Handles group-related activities, including listing groups, CRUD for groups, etc.
 *
 * @package UserFrosting
 * @author Alex Weissman
 * @link http://www.userfrosting.com/navigating/#structure
 */
class GroupController extends \UserFrosting\BaseController {

    /**
     * Create a new GroupController object.
     *
     * @param UserFrosting $app The main UserFrosting app.
     */
    public function __construct($app){
        $this->_app = $app;
    }

    /**
     * Renders the group listing page.
     *
     * This page renders a table of user groups, with dropdown menus for modifying those groups.
     * This page requires authentication (and should generally be limited to admins or the root user).
     * Request type: GET
     * @todo implement interface to modify authorization hooks and permissions
     */
    public function pageGroups(){
        // Access-controlled page
        if (!$this->_app->user->checkAccess('uri_groups')){
            $this->_app->notFound();
        }

        $groups = Group::queryBuilder()->get();

        $this->_app->render('groups/groups.twig', [
            "groups" => $groups
        ]);
    }

    public function pageDashboard(){
        // Access-controlled page
        if (!$this->_app->user->checkAccess('uri_dashboard')){
            $this->_app->notFound();
        }

        $userpgi = $this->_app->user->primary_group_id;
        $usergroupname = Group::queryBuilder()->where('id', $userpgi)->get();

        $this->_app->render('dashboard.twig', [
            "usergroupname" => $usergroupname         
        ]);
    }

    public function pageGroupAuthorization($group_id) {
        // Access-controlled page
        if (!$this->_app->user->checkAccess('uri_authorization_settings')){
            $this->_app->notFound();
        }

        $group = Group::find($group_id);

        // Load all auth rules
        $rules = GroupAuth::where('group_id', $group_id)->get();

        $this->_app->render('config/authorization.twig', [
            "group" => $group,
            "rules" => $rules
        ]);

    }

    /**
     * Renders the form for creating a new group.
     *
     * This does NOT render a complete page.  Instead, it renders the HTML for the form, which can be embedded in other pages.
     * The form can be rendered in "modal" (for popup) or "panel" mode, depending on the value of the GET parameter `render`
     * This page requires authentication (and should generally be limited to admins or the root user).
     * Request type: GET
     */
    public function formGroupCreate(){
        // Access-controlled resource
        if (!$this->_app->user->checkAccess('create_group')){
            $this->_app->notFound();
        }

        $get = $this->_app->request->get();

        if (isset($get['render']))
            $render = $get['render'];
        else
            $render = "modal";

        // Get a list of all themes
        $theme_list = $this->_app->site->getThemes();

        // Set default values
        $data['is_default'] = "0";
        // Set default title for new users
        $data['new_user_title'] = "New User";
        // Set default theme
        $data['theme'] = "default";
        // Set default icon
        $data['icon'] = "fa fa-user";
        // Set default landing page
        $data['landing_page'] = "dashboard";

        // Create a dummy Group to prepopulate fields
        $group = new Group($data);

        if ($render == "modal")
            $template = "components/common/group-info-modal.twig";
        else
            $template = "components/common/group-info-panel.twig";

        // Determine authorized fields
        $fields = ['name', 'new_user_title', 'landing_page', 'theme', 'is_default', 'icon'];
        $show_fields = [];
        $disabled_fields = [];
        foreach ($fields as $field){
            if ($this->_app->user->checkAccess("update_group_setting", ["property" => $field]))
                $show_fields[] = $field;
            else
                $disabled_fields[] = $field;
        }

        // Load validator rules
        $schema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/group-create.json");
        $this->_app->jsValidator->setSchema($schema);

        $this->_app->render($template, [
            "box_id" => $get['box_id'],
            "box_title" => "New Group",
            "submit_button" => "Create group",
            "form_action" => $this->_app->site->uri['public'] . "/groups",
            "group" => $group,
            "themes" => $theme_list,
            "fields" => [
                "disabled" => $disabled_fields,
                "hidden" => []
            ],
            "buttons" => [
                "hidden" => [
                    "edit", "delete"
                ]
            ],
            "validators" => $this->_app->jsValidator->rules()
        ]);
    }

    /**
     * Renders the form for editing an existing group.
     *
     * This does NOT render a complete page.  Instead, it renders the HTML for the form, which can be embedded in other pages.
     * The form can be rendered in "modal" (for popup) or "panel" mode, depending on the value of the GET parameter `render`.
     * Any fields that the user does not have permission to modify will be automatically disabled.
     * This page requires authentication (and should generally be limited to admins or the root user).
     * Request type: GET
     * @param int $group_id the id of the group to edit.
     */
    public function formGroupEdit($group_id){
        // Access-controlled resource
        if (!$this->_app->user->checkAccess('uri_groups')){
            $this->_app->notFound();
        }

        $get = $this->_app->request->get();

        if (isset($get['render']))
            $render = $get['render'];
        else
            $render = "modal";

        // Get the group to edit
        $group = Group::find($group_id);

        // Get a list of all themes
        $theme_list = $this->_app->site->getThemes();

        if ($render == "modal")
            $template = "components/common/group-info-modal.twig";
        else
            $template = "components/common/group-info-panel.twig";

        // Determine authorized fields
        $fields = ['name', 'new_user_title', 'landing_page', 'theme', 'is_default'];
        $show_fields = [];
        $disabled_fields = [];
        $hidden_fields = [];
        foreach ($fields as $field){
            if ($this->_app->user->checkAccess("update_group_setting", ["property" => $field]))
                $show_fields[] = $field;
            else if ($this->_app->user->checkAccess("view_group_setting", ["property" => $field]))
                $disabled_fields[] = $field;
            else
                $hidden_fields[] = $field;
        }

        // Load validator rules
        $schema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/group-update.json");
        $this->_app->jsValidator->setSchema($schema);

        $this->_app->render($template, [
            "box_id" => $get['box_id'],
            "box_title" => "Edit Group",
            "submit_button" => "Update group",
            "form_action" => $this->_app->site->uri['public'] . "/groups/g/$group_id",
            "group" => $group,
            "themes" => $theme_list,
            "fields" => [
                "disabled" => $disabled_fields,
                "hidden" => $hidden_fields
            ],
            "buttons" => [
                "hidden" => [
                    "edit", "delete"
                ]
            ],
            "validators" => $this->_app->jsValidator->rules()
        ]);
    }

    /**
     * Processes the request to create a new group.
     *
     * Processes the request from the group creation form, checking that:
     * 1. The group name is not already in use;
     * 2. The user has the necessary permissions to update the posted field(s);
     * 3. The submitted data is valid.
     * This route requires authentication (and should generally be limited to admins or the root user).
     * Request type: POST
     * @see formGroupCreate
     */
    public function createGroup(){
        $post = $this->_app->request->post();

        // DEBUG: view posted data
        //error_log(print_r($post, true));

        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/group-create.json");

        // Get the alert message stream
        $ms = $this->_app->alerts;

        // Access-controlled resource
        if (!$this->_app->user->checkAccess('create_group')){
            $ms->addMessageTranslated("danger", "ACCESS_DENIED");
            $this->_app->halt(403);
        }

        // Set up Fortress to process the request
        $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $post);

        // Sanitize data
        $rf->sanitize();

        // Validate, and halt on validation errors.
        $error = !$rf->validate(true);

        // Get the filtered data
        $data = $rf->data();

        // Remove csrf_token from object data
        $rf->removeFields(['csrf_token']);

        // Perform desired data transformations on required fields.
        $data['name'] = trim($data['name']);
        $data['new_user_title'] = trim($data['new_user_title']);
        $data['landing_page'] = strtolower(trim($data['landing_page']));
        $data['theme'] = trim($data['theme']);
        $data['can_delete'] = 1;

        // Check if group name already exists
        if (Group::where('name', $data['name'])->first()){
            $ms->addMessageTranslated("danger", "GROUP_NAME_IN_USE", $post);
            $error = true;
        }

        // Halt on any validation errors
        if ($error) {
            $this->_app->halt(400);
        }

        // Set default values if not specified or not authorized
        if (!isset($data['theme']) || !$this->_app->user->checkAccess("update_group_setting", ["property" => "theme"]))
            $data['theme'] = "default";

        if (!isset($data['new_user_title']) || !$this->_app->user->checkAccess("update_group_setting", ["property" => "new_user_title"])) {
            // Set default title for new users
            $data['new_user_title'] = "New User";
        }

        if (!isset($data['landing_page']) || !$this->_app->user->checkAccess("update_group_setting", ["property" => "landing_page"])) {
            $data['landing_page'] = "dashboard";
        }

        if (!isset($data['icon']) || !$this->_app->user->checkAccess("update_group_setting", ["property" => "icon"])) {
            $data['icon'] = "fa fa-user";
        }

        if (!isset($data['is_default']) || !$this->_app->user->checkAccess("update_group_setting", ["property" => "is_default"])) {
            $data['is_default'] = "0";
        }

        // Create the group
        $group = new Group($data);

        // Store new group to database
        $group->store();

        // Success message
        $ms->addMessageTranslated("success", "GROUP_CREATION_SUCCESSFUL", $data);
    }

    /**
     * Processes the request to update an existing group's details.
     *
     * Processes the request from the group update form, checking that:
     * 1. The group name is not already in use;
     * 2. The user has the necessary permissions to update the posted field(s);
     * 3. The submitted data is valid.
     * This route requires authentication (and should generally be limited to admins or the root user).
     * Request type: POST
     * @param int $group_id the id of the group to edit.
     * @see formGroupEdit
     */
    public function updateGroup($group_id){
        $post = $this->_app->request->post();

        // DEBUG: view posted data
        //error_log(print_r($post, true));

        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/group-update.json");

        // Get the alert message stream
        $ms = $this->_app->alerts;

        // Get the target group
        $group = Group::find($group_id);

        // If desired, put route-level authorization check here

        // Remove csrf_token
        unset($post['csrf_token']);

        // Check authorization for submitted fields, if the value has been changed
        foreach ($post as $name => $value) {
            if ($group->attributeExists($name) && $post[$name] != $group->$name){
                // Check authorization
                if (!$this->_app->user->checkAccess('update_group_setting', ['group' => $group, 'property' => $name])){
                    $ms->addMessageTranslated("danger", "ACCESS_DENIED");
                    $this->_app->halt(403);
                }
            } else if (!$group->attributeExists($name)) {
                $ms->addMessageTranslated("danger", "NO_DATA");
                $this->_app->halt(400);
            }
        }

        // Check that name is not already in use
        if (isset($post['name']) && $post['name'] != $group->name && Group::where('name', $post['name'])->first()){
            $ms->addMessageTranslated("danger", "GROUP_NAME_IN_USE", $post);
            $this->_app->halt(400);
        }

        // TODO: validate landing page route, theme, icon?

        // Set up Fortress to process the request
        $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $post);

        // Sanitize
        $rf->sanitize();

        // Validate, and halt on validation errors.
        if (!$rf->validate()) {
            $this->_app->halt(400);
        }

        // Get the filtered data
        $data = $rf->data();

        // Update the group and generate success messages
        foreach ($data as $name => $value){
            if ($value != $group->$name){
                $group->$name = $value;
                // Add any custom success messages here
            }
        }

        $ms->addMessageTranslated("success", "GROUP_UPDATE", ["name" => $group->name]);
        $group->store();

    }

    /********** ACTAS DEMO **********/

    public function pageActas(){
           // Access-controlled resource
           if (!$this->_app->user->checkAccess('uri_forms-actas')){
               $this->_app->notFound();
           }

           

            // Get the validation rules for the form on this page
           $schema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/test.json");
           $this->_app->jsValidator->setSchema($schema); 

           $userpgi = $this->_app->user->primary_group_id;
           $actas = Actas::queryBuilder()->where('primary_group_id', $userpgi)->get();

           $cianame_ac_collection = collect($actas)->lists('cianame');
           $cianame_ac_array = array();
           foreach($cianame_ac_collection as $name111) {
            $cianame_ac_array[] = $name111;
           };

           $accdata_ac_collection = collect($actas)->lists('accdata');
           $accdata_ac_array = array();
           foreach($accdata_ac_collection as $name111) {
            $accdata_ac_array[] = $name111;
           };
            foreach($accdata_ac_array as $key=>$value) {
            $accdata_ac_array[$key] = preg_replace('!,+[0-9.]+!', '',$value);
            };

            $temparray1 = array();
            $temparray2 = array();
            $temparray3 = array();

            foreach ($accdata_ac_array as $key => $value) {
                if (strpos($value, ";") !== false) {
                    $temparray1[] = $value;
                    unset($accdata_ac_array[$key]);
                }
            };

            foreach ($temparray1 as $key => $value) {
                $temparray2 = explode(";", $value);
                $temparray3 = array_merge($temparray3, $temparray2);
            };

            $accdata_ac_array = array_merge($accdata_ac_array, $temparray3);

            foreach($accdata_ac_array as $key=>$value) {
            $accdata_ac_array[$key] = preg_replace('/[,]$/', '',$value);
            };

           $this->_app->render('forms-actas.twig', [
            "actas" => $actas,
            "cianameacarray" => $cianame_ac_array,
            "accdataacarray" => $accdata_ac_array,
           "validators" => $this->_app->jsValidator->rules()         
           ]);  
                        $userfn1 = $this->_app->user->user_name;
                        include "downloadfilex.php";
                        $filex1 = $userfn1 . '.docx';
                        if (file_exists($filex1)) {
                            downloadfilex($filex1);
                            unlink($filex1);}
        }

    public function createActa(){
           // Access-controlled resource
           if (!$this->_app->user->checkAccess('uri_forms-actas')){
               $this->_app->notFound();
           }
                      
           $userfn1 = $this->_app->user->user_name;
           // Fetch the POSTed data
           $post = $this->_app->request->post();
           
           // Load the request schema
           $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/test.json");
           
           // Get the alert message stream
           $ms = $this->_app->alerts; 
        
           // Set up Fortress to process the request
           $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $post);                    
            
           // Sanitize
           $rf->sanitize();
            
           // Validate, and halt on validation errors.
           if (!$rf->validate()) {
               $this->_app->halt(400);
           }   
                  
           // Get the filtered data
           $data = $rf->data();
            

            //--este es el tipo de cia--
            $ciatype = $data['ciatype'];
            if ($ciatype == "S.A.") {
                $acctype = "accionistas";
                $accxtypemay = "Accionistas";
                $partcaptype = "acción(es)";
            };
            if ($ciatype == "LTDA") {
                $acctype = "socios";
                $accxtypemay = "Socios";
                $partcaptype = "participación(es)";
            };

            //--este es el nombre de la cia--
            $cianombre = $data['actademo_cianombre'];
            $cianombre = str_replace("&", "&amp;", $cianombre);


            //--aca pongo la fecha y hora en un formato usable--
            list($dia, $mes, $añoyhora) = explode("/", $data['actademo_chosendate']);

            list($año, $hora) = explode("   ", $añoyhora);

            $mesennums = ["01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "11", "12"];
            $mesenletras = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];

            $mes = str_replace($mesennums, $mesenletras, $mes);

            $fecha1 = $dia . " de " . $mes . " de " . $año;
            $añoanterior1 = $año - 1;

            //--esto se usará luego para reemplazar texto en el archivo dependiendo del # de accionistas--

            if ($data['accnumber'] == 1) {
                $vara011 = "UNO) acc1nombre";
                $vara01coma = ", ";
                $vara012 = 'por sus propios derechos, propietario de acc1acciones partcaptype de USD $valnominalxx cada una, pagadas en el 100.00% de su valor nominal y con derecho a acc1votos voto(s).';
                $vara021 = "";
                $vara02coma = "";
                $vara022 = "";

            } elseif ($data['accnumber'] == 2) {
                $vara011 = "UNO) acc1nombre";
                $vara01coma = ", ";
                $vara012 = 'por sus propios derechos, propietario de acc1acciones partcaptype de USD $valnominalxx cada una, pagadas en el 100.00% de su valor nominal y con derecho a acc1votos voto(s); y ';
                $vara021 = "DOS) acc2nombre";
                $vara02coma = ", ";
                $vara022 = 'por sus propios derechos, propietario de acc2acciones partcaptype de USD $valnominalxx cada una, pagadas en el 100.00% de su valor nominal y con derecho a acc2votos voto(s).';

                //temporal xq tvia no tengo archivo con 3 accionistas
             } elseif ($data['accnumber'] == 3) {
                $vara011 = "UNO) acc1nombre";
                $vara01coma = ", ";
                $vara012 = 'por sus propios derechos, propietario de acc1acciones partcaptype de USD $valnominalxx cada una, pagadas en el 100.00% de su valor nominal y con derecho a acc1votos voto(s); y ';
                $vara021 = "DOS) acc2nombre";
                $vara02coma = ", ";
                $vara022 = 'por sus propios derechos, propietario de acc2acciones partcaptype de USD $valnominalxx cada una, pagadas en el 100.00% de su valor nominal y con derecho a acc2votos voto(s).';

            } 

            //--estos son el capital y el valor nominal de las acciones de la cia--
            $capital = $data['actademo_capital'];
            $valnominal = $data['actademo_valnominal'];

            //--estos son los datos del accionista 1--
            $acc1nombre = $data['actademo_acc1nombre'];
            $acc1nombre = str_replace("&", "&amp;", $acc1nombre);
            //$acc1nombre = mb_strtoupper($acc1nombre, "UTF-8"); <-- usar esto si prefiero auto uppcasear esta waa

            $acc1acciones = $data['actademo_acc1acciones'];
            $acc1valnominal = $acc1acciones * $valnominal;
            $acc1votos = $acc1acciones;


            //--estos son los datos del accionista 2--
            $acc2nombre = $data['actademo_acc2nombre'];
            $acc2nombre = str_replace("&", "&amp;", $acc2nombre);
            //$acc2nombre = mb_strtoupper($acc2nombre, "UTF-8"); <-- usar esto si prefiero auto uppcasear esta waa

            $acc2acciones = $data['actademo_acc2acciones'];
            $acc2valnominal = $acc2acciones * $valnominal;
            $acc2votos = $acc2acciones;

            //--estos son los datos del accionista 3--
            $acc3nombre = $data['actademo_acc3nombre'];
            $acc3nombre = str_replace("&", "&amp;", $acc3nombre);
            //$acc2nombre = mb_strtoupper($acc2nombre, "UTF-8"); <-- usar esto si prefiero auto uppcasear esta waa

            $acc3acciones = $data['actademo_acc3acciones'];
            $acc3valnominal = $acc3acciones * $valnominal;
            $acc3votos = $acc3acciones;

            //--estos son los totales de acciones y votos--//
            $totaldeacciones = $acc1acciones + $acc2acciones + $acc3acciones;
            $totaldevotos = $acc1votos + $acc2votos + $acc3votos;

            //--aca esta todo lo que tiene q ver con reemplazar las waas en el archivo final.--

            //esto determina que archivo modelo hay que usar
            $file = 'modeloacta.docx';
            $newfile = $userfn1 . '.docx';

            copy($file, $newfile);

            $zip = new ZipArchive;
            //This is the main document in a .docx file.
            $fileToModify = 'word/document.xml';
            $wordDoc = $userfn1 . '.docx';

            $vararemp = ["vara011", "vara01coma", "vara012", "vara021", "vara02coma", "vara022", "cianombre", "hora1x", "acc1nombre", "acc1acciones", "acc2nombre", "acc2acciones", "acctype", "accxtypemay", "partcaptype", "añoanterior1", "currentyear1", "totalvotosxx", "capitalvarxx", "valnominalxx", "acc1votos", "acc2votos"];
            $userinputvar = [$vara011, $vara01coma, $vara012, $vara021, $vara02coma, $vara022, $cianombre, $hora, $acc1nombre, $acc1acciones, $acc2nombre, $acc2acciones, $acctype, $accxtypemay, $partcaptype, $añoanterior1, $año, $totaldevotos, $capital, $valnominal, $acc1votos, $acc2votos];

            if ($zip->open($wordDoc) === TRUE) {
            //Read contents into memory
            $oldContents = $zip->getFromName($fileToModify);
            //Modify contents:
            $newContents = str_replace($vararemp, $userinputvar, $oldContents);

            if ($data['accnumber'] == 1) {
            $newContents = preg_replace('!<w:tr w:rsidR="00530FAC"[\s\S]+?</w:tr>!', '', $newContents);
            $newContents = preg_replace('!<w:p w:rsidR="000C42BC" w:rsidRPr="00073BCA" w:rsidRDefault="00FE268B" w:rsidP="00FE268B[\s\S]+?</w:p>!', '', $newContents);
            $newContents = preg_replace('!<w:p w:rsidQ="ESPENT1Y2"[\s\S]+?</w:p>!', '', $newContents);
            }

            //Delete the old...
            $zip->deleteName($fileToModify);
            //Write the new...
            $zip->addFromString($fileToModify, $newContents);
            //And write back to the filesystem.
            $return = $zip->close();
            }

            $zip = new ZipArchive;
            $fileToModify = 'word/header2.xml';
            $wordDoc = $userfn1 . '.docx';

            $vararemp = ["fechaheader", "cianombre", "accxtypemay"];
            $userinputvar = [$fecha1, $cianombre, $accxtypemay];

            if ($zip->open($wordDoc) === TRUE) {
            //Read contents into memory
            $oldContents = $zip->getFromName($fileToModify);
            //Modify contents:
            $newContents = str_replace($vararemp, $userinputvar, $oldContents);
            //Delete the old...
            $zip->deleteName($fileToModify);
            //Write the new...
            $zip->addFromString($fileToModify, $newContents);
            //And write back to the filesystem.
            $return = $zip->close();
            }

            //--Guardar los datos en la db--
            $makedate1 = new \DateTime("now", new \DateTimeZone('America/Guayaquil') );
            $makedate1 = $makedate1->format('Y-m-d H:i:s');

            if ($data['accnumber'] == 1) {
                $accdata = $acc1nombre . "," . $acc1acciones;
            } elseif ($data['accnumber'] == 2) {
                $accdata = $acc1nombre . "," . $acc1acciones . ";" . $acc2nombre . "," . $acc2acciones;
            } elseif ($data['accnumber'] == 3) {
                $accdata = $acc1nombre . "," . $acc1acciones . ";" . $acc2nombre . "," . $acc2acciones . ";" . $acc3nombre . "," . $acc3acciones;
            }

            $actas_form2db = new Actas([
                "makedate" => $makedate1,
                "cianame" => $cianombre,
                "ciatype" => $ciatype,
                "celebdate" => $data['actademo_chosendate'],
                "accnumber" => $data['accnumber'],
                "primary_group_id" => $this->_app->user->primary_group_id,
                "user_name" => $this->_app->user->user_name,
                "accdata" => $accdata

            ]);

            $actas_form2db ->save();
            $id = $actas_form2db->id;
        }


    /********** MINUTAS DEMO **********/
    public function pageMinutas1(){
           // Access-controlled resource
           if (!$this->_app->user->checkAccess('uri_forms-minutas1')){
               $this->_app->notFound();
           }

           

            // Get the validation rules for the form on this page
           $schema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/minutas1.json");
           $this->_app->jsValidator->setSchema($schema); 

           $userpgi = $this->_app->user->primary_group_id;
           $minutas1 = Minutas1::queryBuilder()->get();

 

           $this->_app->render('forms-minutas1.twig', [
            "minutas1" => $minutas1,
           "validators" => $this->_app->jsValidator->rules()         
           ]);  
                        $userfn1 = $this->_app->user->user_name;
                        include "downloadfilex.php";
                        $filex1 = $userfn1 . '.docx';
                        if (file_exists($filex1)) {
                            downloadfilex($filex1);
                            unlink($filex1);}
        }

    public function createMinutas1(){
           // Access-controlled resource
           if (!$this->_app->user->checkAccess('uri_forms-minutas1')){
               $this->_app->notFound();
           }
                      
           $userfn1 = $this->_app->user->user_name;
           // Fetch the POSTed data
           $post = $this->_app->request->post();
           
           // Load the request schema
           $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/minutas1.json");
           
           // Get the alert message stream
           $ms = $this->_app->alerts; 
        
           // Set up Fortress to process the request
           $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $post);                    
            
           // Sanitize
           $rf->sanitize();
            
           // Validate, and halt on validation errors.
           if (!$rf->validate()) {
               $this->_app->halt(400);
           }   
                  
           // Get the filtered data
           $data = $rf->data();
            
           //aquí consigo los datos de la propiedad a vender en base a la alícuota elegida por el usuario en el formulario

           $aliid = $data['minutademo_alicuota'];
           $aliinfoarr = Minutas1::where('id', $aliid)->get();
           $alicuotaxx = $aliinfoarr[0]["alicuota"];
           $aliinfo1 = $aliinfoarr[0]["info1"];
           $aliinfo2 = $aliinfoarr[0]["info2"];
           $aliinfo3 = $aliinfoarr[0]["info3"];
           $aliinfo4 = $aliinfoarr[0]["info4"];
           $aliinfo5 = $aliinfoarr[0]["info5"];

           //aquí transformo los precios en letras y los pongo en el formato que se necesita
           include "num2letters.php";
           $preciocvraw = $data['minutademo_preciodecv'];
           $preciocvcentavos = substr($preciocvraw, -2);
           $preciocvdolares = substr($preciocvraw, 0, -3);
           $preciocvdolaresnopunct = str_replace(".", "", $preciocvdolares);
           $preciocvdolaresnopunct = str_replace(",", "", $preciocvdolaresnopunct);
           $preciocvdolaresletras = numtoletras($preciocvdolaresnopunct);
           $preciocvfinal = $preciocvdolaresletras . " DOLARES DE LOS ESTADOS UNIDOS DE AMERICA" . " " . $preciocvcentavos . "/100" . " " . "(" . "$" . $preciocvraw . ")";

           $montodpraw = $data['minutademo_montodp'];
           $montodpcentavos = substr($montodpraw, -2);
           $montodpdolares = substr($montodpraw, 0, -3);
           $montodpdolaresnopunct = str_replace(".", "", $montodpdolares);
           $montodpdolaresnopunct = str_replace(",", "", $montodpdolaresnopunct);
           $montodpdolaresletras = numtoletras($montodpdolaresnopunct);
           $montodpfinal = $montodpdolaresletras . " DOLARES DE LOS ESTADOS UNIDOS DE AMERICA" . " " . $montodpcentavos . "/100" . " " . "(" . "$" . $montodpraw . ")";

           $montopresraw = $data['minutademo_montopres'];
           $montoprescentavos = substr($montopresraw, -2);
           $montopresdolares = substr($montopresraw, 0, -3);
           $montopresdolaresnopunct = str_replace(".", "", $montopresdolares);
           $montopresdolaresnopunct = str_replace(",", "", $montopresdolaresnopunct);
           $montopresdolaresletras = numtoletras($montopresdolaresnopunct);
           $montopresfinal = $montopresdolaresletras . " DOLARES DE LOS ESTADOS UNIDOS DE AMERICA" . " " . $montoprescentavos . "/100" . " " . "(" . "$" . $montopresraw . ")";

           $precioebraw = $data['minutademo_precioeb'];
           $precioebcentavos = substr($precioebraw, -2);
           $precioebdolares = substr($precioebraw, 0, -3);
           $precioebdolaresnopunct = str_replace(".", "", $precioebdolares);
           $precioebdolaresnopunct = str_replace(",", "", $precioebdolaresnopunct);
           $precioebdolaresletras = numtoletras($precioebdolaresnopunct);
           $precioebfinal = $precioebdolaresletras . " DOLARES DE LOS ESTADOS UNIDOS DE AMERICA" . " " . $precioebcentavos . "/100" . " " . "(" . "$" . $precioebraw . ")";

           //aquí pongo el input del usuario en variables para luego reemplazar esas variables por el texto en el modelo
           $con1xx = $data['minutademo_conyuge1name'];
           $con2xx = $data['minutademo_conyuge2name'];
           $ifinxx = $data['minutademo_ifin'];

            //esto determina que archivo modelo hay que usar
            $file = 'minuta1modelo1.docx';
            $newfile = $userfn1 . '.docx';

            copy($file, $newfile);

            $zip = new ZipArchive;
            //This is the main document in a .docx file.
            $fileToModify = 'word/document.xml';
            $wordDoc = $userfn1 . '.docx';

            $vararemp = ["con1xx", "con2xx", "alicuotaxx", "info1xx", "info2xx", "info3xx", "info4xx", "info5xx", "preciodecvxx", "montodpxx", "montopresxx", "precioebxx", "ifinxx"];
            $userinputvar = [$con1xx, $con2xx, $alicuotaxx, $aliinfo1, $aliinfo2, $aliinfo3, $aliinfo4, $aliinfo5, $preciocvfinal, $montodpfinal, $montopresfinal, $precioebfinal, $ifinxx];

            if ($zip->open($wordDoc) === TRUE) {
            //Read contents into memory
            $oldContents = $zip->getFromName($fileToModify);
            //Modify contents:

            if ($data['minutademo_tipodecomprador'] == "Cónyuges") {
            $oldContents = preg_replace('!<w:t>los  cónyuges</w:t></w:r><w:r w:rsidRPr="00BA360B"[\s\S]+?la  sociedad  conyugal  que  tienen  formada</w:t>!', '<w:t>los  cónyuges</w:t></w:r><w:r w:rsidRPr="001E203C"><w:rPr><w:rFonts w:ascii="Palatino Linotype" w:eastAsia="Calibri" w:hAnsi="Palatino Linotype" w:cs="Calibri"/><w:b/><w:spacing w:val="20"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="es-ES" w:eastAsia="es-ES"/></w:rPr><w:t xml:space="preserve"> </w:t></w:r><w:r><w:rPr><w:rFonts w:ascii="Palatino Linotype" w:eastAsia="Calibri" w:hAnsi="Palatino Linotype" w:cs="Calibri"/><w:b/><w:bCs/><w:spacing w:val="20"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="es-ES" w:eastAsia="es-ES"/></w:rPr><w:t>con1xx</w:t></w:r><w:r w:rsidRPr="001E203C"><w:rPr><w:rFonts w:ascii="Palatino Linotype" w:eastAsia="Calibri" w:hAnsi="Palatino Linotype" w:cs="Calibri"/><w:b/><w:bCs/><w:spacing w:val="20"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="es-ES" w:eastAsia="es-ES"/></w:rPr><w:t xml:space="preserve"> y </w:t></w:r><w:r><w:rPr><w:rFonts w:ascii="Palatino Linotype" w:eastAsia="Calibri" w:hAnsi="Palatino Linotype" w:cs="Calibri"/><w:b/><w:bCs/><w:spacing w:val="20"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="es-ES" w:eastAsia="es-ES"/></w:rPr><w:t>con2xx</w:t></w:r><w:bookmarkStart w:id="0" w:name="_GoBack"/><w:bookmarkEnd w:id="0"/><w:r w:rsidRPr="001E203C"><w:rPr><w:rFonts w:ascii="Palatino Linotype" w:eastAsia="Calibri" w:hAnsi="Palatino Linotype" w:cs="Calibri"/><w:spacing w:val="20"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="es-ES" w:eastAsia="es-ES"/></w:rPr><w:t xml:space="preserve">  por  sus  propios  y  personales  derechos  y  por  los  que  representan  de  la  sociedad  conyugal  que  tienen  formada</w:t>', $oldContents);
            }

            $newContents = str_replace($vararemp, $userinputvar, $oldContents);

            //Delete the old...
            $zip->deleteName($fileToModify);
            //Write the new...
            $zip->addFromString($fileToModify, $newContents);
            //And write back to the filesystem.
            $return = $zip->close();
            }

            /*//--Guardar los datos en la db--
            $makedate1 = new \DateTime("now", new \DateTimeZone('America/Guayaquil') );
            $makedate1 = $makedate1->format('Y-m-d H:i:s');

            if ($data['accnumber'] == 1) {
                $accdata = $acc1nombre . "," . $acc1acciones;
            } elseif ($data['accnumber'] == 2) {
                $accdata = $acc1nombre . "," . $acc1acciones . ";" . $acc2nombre . "," . $acc2acciones;
            } elseif ($data['accnumber'] == 3) {
                $accdata = $acc1nombre . "," . $acc1acciones . ";" . $acc2nombre . "," . $acc2acciones . ";" . $acc3nombre . "," . $acc3acciones;
            }

            $actas_form2db = new Actas([
                "makedate" => $makedate1,
                "cianame" => $cianombre,
                "ciatype" => $ciatype,
                "celebdate" => $data['actademo_chosendate'],
                "accnumber" => $data['accnumber'],
                "primary_group_id" => $this->_app->user->primary_group_id,
                "user_name" => $this->_app->user->user_name,
                "accdata" => $accdata

            ]);

            $actas_form2db ->save();
            $id = $actas_form2db->id;*/
        }



    /**
     * Processes the request to delete an existing group.
     *
     * Deletes the specified group, removing associations with any users and any group-specific authorization rules.
     * Before doing so, checks that:
     * 1. The group is deleteable (as specified in the `can_delete` column in the database);
     * 2. The group is not currently set as the default primary group;
     * 3. The submitted data is valid.
     * This route requires authentication (and should generally be limited to admins or the root user).
     * Request type: POST
     * @param int $group_id the id of the group to delete.
     */
    public function deleteGroup($group_id){
        $post = $this->_app->request->post();

        // Get the target group
        $group = Group::find($group_id);

        // Get the alert message stream
        $ms = $this->_app->alerts;

        // Check authorization
        if (!$this->_app->user->checkAccess('delete_group', ['group' => $group])){
            $ms->addMessageTranslated("danger", "ACCESS_DENIED");
            $this->_app->halt(403);
        }

        // Check that we are allowed to delete this group
        if ($group->can_delete == "0"){
            $ms->addMessageTranslated("danger", "CANNOT_DELETE_GROUP", ["name" => $group->name]);
            $this->_app->halt(403);
        }

        // Do not allow deletion if this group is currently set as the default primary group
        if ($group->is_default == GROUP_DEFAULT_PRIMARY){
            $ms->addMessageTranslated("danger", "GROUP_CANNOT_DELETE_DEFAULT_PRIMARY", ["name" => $group->name]);
            $this->_app->halt(403);
        }

        $ms->addMessageTranslated("success", "GROUP_DELETION_SUCCESSFUL", ["name" => $group->name]);
        $group->delete();       // TODO: implement Group function
        unset($group);
    }

}
