<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Petition manifest controller.
 *
 * @author    Tim Joosten   <Topairy@gmail.com>
 * @copyright Activisme-BE  <info@activisme.be>
 * @license:  MIT license
 * @since     2017
 * @package   Petitions
 */
class Manifest extends MY_Controller
{
    // TODO: [V1.0.0-RC1] Create function that export the signatures to pdf. 
    // TODO: [V1.0.0-RC1] Create function that returns the petition id. This is needed for the ajax calls. 
    // TODO: [V1.0.0-RC1] Register the auth middleware. In the middleware function. 
    // TODO: [V1.0.0-RC2] Register language files to the controller titles and flash messages. 
    // TODO: [V1.0.0-RC2] Register language files to the views.

    public $user        = [];   /** @var array $user         The userdata about the authencated user.  */
    public $permissions = [];   /** @var array $permissions  The authencated user permissions.         */
    public $abilities   = [];   /** @var array $abilities    The authencated user abilities.           */

	/**
	 * Manifest constructor.
     * 
     * @return int|void|null
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->library(['session', 'form_validation', 'blade', 'pagination', 'paginator']);
		$this->load->helper(['url']);

		$this->user        = $this->session->userdata('user');
		$this->permissions = $this->session->userdata('permissions');
		$this->abilities   = $this->session->userdata('abilities');
	}

    /**
     * Return the list of middlewares you want to be applied,
     * Here is list of some valid options
     *
     * admin_auth                    // As used below, simplest, will be applied to all
     * someother|except:index,list   // This will be only applied to posts()
     * yet_another_one|only:index    // This will be only applied to index()
     *
     * @return array
     */
    protected function middleware()
    {
        return []; // TODO: Build up the authencation middleware protection.
    }

    /**
     * Display all the petitions in the system.
     *
     * @see:url('GET|HEAD', 'http://www.petities.activisme.be/manifest')
     * @return string
     */
	public function index()
	{
        $data['title']    = 'Manifest backend';
        $data['recent']   = Petitions::all(); // TODO: Build up the where statements for the query. 
        $data['popular']  = Petitions::all(); // TODO: Build up the where statements for the query; 

        if ($this->user) {
            $data['userPetitions'] = Petitions::where('creator_id', $this->user['id'])->get();
        }

        return $this->blade->render('petitions/index', $data);
	}

    /**
     * Show a specific petition.
     *
     * @see:url('GET|HEAD', 'http://www.petities.activisme.be/manifest/show/{id}')
     * @return string
     */
    public function show()
    {
        $petitionId        = $this->security->xss_clean($this->uri->segment(3));

        $data['petition']  = Petitions::with(['comments', 'creator', 'updates'])->find($petitionId);
        $data['title']     = $data['petition']->title;
        $data['countries'] = Countries::all();
        $data['cities']    = Cities::all();

        if ((int) count($data['petition']) === 0) { // No petition found.
            $this->session->set_flashdata('class', 'alert alert-danger');
            $this->session->set_flashdata('message', 'Er is geen petitie gevonden met de id #'. $petitionId);
        }

        // \Illuminate\Database\Capsule\Manager::enableQueryLog();
        // Petitions::with(['updates'])->find($petitionId);
        // dump(\Illuminate\Database\Capsule\Manager::getQueryLog());


        // Comments
        $this->pagination->initialize($this->paginator->relation(base_url('manifest/show/' . $data['petition']->id), $data['petition']->comments()->count(), 4, 4));
        $data['comments']      = $data['petition']->comments()->skip($this->input->get('page'))->take(4)->get();
        $data['comments_link'] = $this->pagination->create_links();

        return $this->blade->render('petitions/show', $data);
    }

    /**
     * Create a new petition into the system.
     *
     * @see:url('GET|HEAD', 'http://www.petities.activisme.be/manifest/create')
     * @return string.
     */
    public function create()
    {
        $data['title']      = 'Nieuwe petitie';
        $data['categories'] = Category::where('category_module', 'petition')->get();

        return $this->blade->render('petitions/create', $data);
    }

    /**
     * Create a new petition in the system.
     *
     * @see:url('POST', 'http://www.petities.activisme.be/manifest/create')
     * @return string
     */
	public function store()
	{
        $this->form_validation->set_rules('title', 'Titel', 'trim|required');
        $this->form_validation->set_rules('description', 'Petitie manifest', 'trim|required');
        $this->form_validation->set_rules('category', 'Categorie', 'trim|required');

        if ($this->form_validation->run() === true) { // Validation passes.
            $input['title']       = $this->input->post('title');
            $input['description'] = $this->input->post('description');
            $input['category_id'] = $this->input->post('category');
            $input['creator_id']  = $this->user['id'];

            if (Petitions::create($this->security->xss_clean($input))) { // Data >>> Stored to the database.
                $this->session->set_flashdata('class', 'alert alert-success');
                $this->session->set_flashdata('message', 'Wij hebben de petitie aangemaakt');
            }

            return $this->blade->render('petitions/create');
        }

        $this->session->set_flashdata('class', 'alert alert-danger');
        $this->session->set_flashdata('message', 'Wij konden de petitie niet aanmeken');

        return $this->blade->render('petitions/create');
	}

    /**
     * Sign a petition in the system.
     *
     * @see:url('POST', 'http://www.petities.activisme.be/manifest/sign/{id}')
     * @return mixed
     */
    public function sign()
    {
        $petitionId = $this->security->xss_clean($this->uri->segment(3));

        $this->form_validation->set_rules('name', 'Naam', 'trim|required');
        $this->form_validation->set_rules('email', 'Email', 'trim|required');
        $this->form_validation->set_rules('city', 'Stad', 'trim|required');
        $this->form_validation->set_rules('country', 'Land', 'trim|required');

        if ($this->form_validation->run() === false) { // Validation fails
            // dump(validation_errors());	// Only for debugging proposer
            // die();						// Only for debugging propose.

            $data['petition']  = Petitions::with(['comments'])->find($petitionId);
            $data['title']     = $data['petition']->title;
            $data['countries'] = Countries::all();
            $data['cities']    = Cities::all();

            if ((int) count($data['petition']) === 0) { // No petition found.
                $this->session->set_flashdata('class', 'alert alert-danger');
                $this->session->set_flashdata('message', 'Er is geen petitie gevonden met de id #'. $petitionId);
            }

            $this->pagination->initialize($this->paginator->relation(base_url('manifest/show/' . $data['petition']->id), $data['petition']->comments()->count(), 4, 4));
            $data['comments']      = $data['petition']->comments()->skip($this->input->get('page'))->take(4)->get();
            $data['comments_link'] = $this->pagination->create_links();

            return $this->blade->render('petitions/show', $data);
        }

        // Validation passes. Move on with the controller logic.
        $input['name']       = $this->input->post('name');
        $input['email']      = $this->input->post('email');
        $input['city_id']    = $this->input->post('city');
        $input['country_id'] = $this->input->post('country');
        $input['publish']    = $this->input->post('publish');

        $MySQL['sign']   = Signature::create($this->security->xss_clean($input));
        $MySQL['assign'] = Petitions::find($petitionId)->signatures()->attach($MySQL['sign']->id);

        if ($MySQL['sign'] && $MySQL['assign']) {
            $this->session->set_flashdata('class', 'alert alert-successs');
            $this->session->set_flashdata('message', 'U hebt deze petitie getekend.');
        }

        return redirect($_SERVER['HTTP_REFERER']);
    }

	/**
	 * Show all the supporters for a manifest.
	 *
	 * @see:url('GET|HEAD', 'http://www.petities.activisme.be/manifest/signatures/{petitionId}')
	 * @return string
	 */
    public function signatures()
    {
        // TODO: Implement pagination.
        
        $petitionId = $this->security->xss_clean($this->uri->segment(3));

        $data['title']      = 'Petitie handtekeningen';
        $data['signatures'] = Petitions::with(['signatures.countryRel', 'signatures.cityRel'])->find($petitionId);

        return $this->blade->render('petitions/signatures', $data);
    }

    /**
     * Get a petition by his id. Needed for AJAX resquests. 
     * 
     * @see:url('GET|HEAD', 'http://www.petities.activisme.be/manifest/getById/{petitionId}')
     * @return JSON Response
     */
    public function getById() 
    {
        echo json_encode(Petitions::find($this->security->xss_clean($this->uri->segment(3))));
    }

    /**
     * Determine the status for the petition. 
     *
     * @see:url('GET|HEAD', 'http://www.petities.activisme.be/manifest/status/{status}/{manifestId}')
     * @return Redirect
     */
    public function status() 
    {
        $petitionId = $this->security->xss_clean($this->uri->segment(4));

        switch ($this->security->xss_clean($this->uri->segment(3))) {
            case 'close': // The petition is closed
                if ($petition = Manifest::find($petitionId)->update(['active' => 'Y', 'closed_at' => date('Y-m-d H:i:s')])) {
                    $this->session->set_flashdata('class', 'alert alert-success');
                    $this->session->set_flashdata('message', 'De petitie is gesloten');
                }

                break;
            case 'reopen': // The petition is re-opened
                if ($petition = Manifest::find($petitionId)->update(['active' => 'N', 'closed_at' => ''])) {
                    $this->session->set_flashdata('class', 'alert alert-success'); 
                    $this->session->set_flashdata('message', 'De petitie is heropend');
                }

                break; 
            default: // Could not determine the handling>. 
                $this->session->set_flashdata('class', 'alert alert-danger'); 
                $this->session->set_flashdata('message', 'Wij konden de handeling niet uitvoeren door een interne fout.');
        }

        return redirect($_SERVER['HTTP_REFERER']);
    }

	/**
	 * Delete a specific petition.
	 *
	 * @see:url('GET|HEAD', 'http://www.petities.activisme.be/manifest/delete/{id}')
	 * @return redirect
	 */
	public function delete()
	{
        $manifestId = $this->uri->segment(3);

        if (Petitions::find($this->security->xss_clean($manifestId))->delete()) { // The record has been deleted.
            $this->session->set_flashdata('class', 'alert alert-success');
            $this->session->set_flashdata('message', 'De petitie is verwijderd');
        }

        return redirect(base_url('manifest'));
	}
}
