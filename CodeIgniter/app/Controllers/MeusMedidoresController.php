<?php

namespace App\Controllers;

use App\Exceptions\FormException;
use App\Models\MedidorModel;
use App\Services\MedidorCustodiaService;
use CodeIgniter\Exceptions\PageNotFoundException;

final class MeusMedidoresController extends ApplicationController
{
    public function index() { return $this->overview(); }
    public function pickup(int $id) { return $this->operation($id, false); }
    public function returnMeter(int $id) { return $this->operation($id, true); }

    private function operation(int $id, bool $return)
    {
        $user=service('auth')->user();
        if (empty($user['id_ele'])) { return $this->response->setStatusCode(403)->setBody('Cadastro técnico inválido.'); }
        $record=(new MedidorModel())->custodyRecord($id);
        if (!$record) { throw PageNotFoundException::forPageNotFound('Medidor não encontrado.'); }
        if (($record['eletricista_posse_med'] !== null && (int)$record['eletricista_posse_med'] !== (int)$user['id_ele']) || ($record['reserva_responsavel'] !== null && (int)$record['reserva_responsavel'] !== (int)$user['id_ele']) || ($return && (int)$record['eletricista_posse_med'] !== (int)$user['id_ele'])) {
            return $this->response->setStatusCode(403)->setBody(view('errors/access',['title'=>'Acesso não permitido','message'=>'Este medidor não está disponível para esta operação por você.']));
        }
        try {
            $service=new MedidorCustodiaService();
            if ($return) { $service->returnMeter($id,$this->request->getPost('condicao'),(int)$user['id_usu']); }
            else { $service->pickup($id,(int)$user['id_usu']); }
            return redirect()->to(site_url('meus-medidores'))->setStatusCode(303)->with('success',$return?'Devolução registrada no depósito.':'Retirada registrada. O medidor está em sua posse.');
        } catch (FormException $e) { return $this->overview($e->errors,422,$id); }
    }

    private function overview(array $errors=[],int $status=200,?int $meter=null)
    {
        $query=$this->request->getGet('q') ?? '';
        if (!is_string($query) || mb_strlen($query)>150) { $query=''; $errors['q']='Informe uma busca com até 150 caracteres.'; $status=422; }
        $electrician=(int)(service('auth')->user()['id_ele'] ?? 0);
        if (!$electrician) { return $this->response->setStatusCode(403)->setBody('Cadastro técnico inválido.'); }
        $available=new MedidorModel(); $owned=new MedidorModel();
        $rows=$errors && isset($errors['q']) ? [] : $available->availableForPickup($electrician,trim($query))->paginate(15,'disponiveis');
        $custody=$errors && isset($errors['q']) ? [] : $owned->inCustody($electrician,trim($query))->paginate(15,'posse');
        $condition=$this->request->getPost('condicao');
        return $this->page('meus-medidores/index',['title'=>'Meus medidores','active'=>'meus-medidores','rows'=>$rows,'custody'=>$custody,'availablePager'=>$available->pager,'custodyPager'=>$owned->pager,'query'=>$query,'errors'=>$errors,'errorMeter'=>$meter,'condition'=>is_string($condition)?$condition:''],$status);
    }
}
