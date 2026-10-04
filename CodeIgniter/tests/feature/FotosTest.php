<?php

namespace Tests\Feature;

use App\Exceptions\FormException;
use App\Models\AnexoModel;
use App\Services\FotoService;
use CodeIgniter\HTTP\Files\UploadedFile;
use Tests\Support\AppTestCase;
use Tests\Support\LocalUploadedPhoto;

final class FotosTest extends AppTestCase
{
    private array $files = [];
    private array $stored = [];
    private FotoService $photos;
    private string $storage;
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'em_atendimento']);
        $this->storage = '/tmp/gpm-photo-storage-' . bin2hex(random_bytes(8));
        config('Fotos')->directory = $this->storage;
        $this->photos = new FotoService($this->db);
    }
    protected function tearDown(): void
    {
        foreach (array_merge($this->files,$this->stored) as $path) { if (is_file($path) || is_link($path)) { unlink($path); } }
        if (is_dir($this->storage . '/1')) { rmdir($this->storage . '/1'); }
        if (is_dir($this->storage)) { rmdir($this->storage); }
        config('Fotos')->directory = WRITEPATH . 'uploads/os-fotos';
        parent::tearDown();
    }
    private function image(string $format='png', int $error=UPLOAD_ERR_OK): LocalUploadedPhoto
    {
        $path=tempnam('/tmp','gpm-photo-'); copy(SUPPORTPATH . 'fixtures/photos/valid.' . $format,$path); $this->files[]=$path;
        return new LocalUploadedPhoto($path,'../../foto.php','text/html',filesize($path),$error);
    }
    private function upload(string $format='png', string $note='Foto em campo'): array
    {
        $id=$this->photos->upload(1,$this->image($format),$note,3);
        $row=$this->db->table('tbl_anexo')->where('id_anx',$id)->get()->getRowArray();
        $this->stored[]=$this->storage . '/' . $row['arquivo_anx']; return $row;
    }
    private function denied(callable $action, string $field='operacao'): void
    {
        try { $action(); $this->fail('Ação inválida aceita.'); } catch (FormException $e) { $this->assertArrayHasKey($field,$e->errors); }
    }
    public function testRoleCsrfExpiredAndWrongVerbProtection(): void
    {
        foreach ([null,1,2,3,4] as $actor) {
            $r=$this->requestAs($actor,'POST','os/1/fotos',['descricao_foto'=>'Teste']);
            if ($actor===null) { $r->assertRedirectTo(site_url('login')); } else { $r->assertStatus($actor===3 ? 422 : 403); }
        }
        foreach (['os/1/fotos','os/1/fotos/1/remover'] as $path) {
            $this->requestAs(3,'POST',$path,[],false)->assertStatus(403);
            $this->requestAs(3,'POST',$path,[],true,time()-7201)->assertRedirectTo(site_url('login'));
            $this->requestAs(3,'GET',$path)->assertStatus(404);
        }
        $this->assertSame(0,$this->db->table('tbl_anexo')->countAllResults());
    }
    public function testByteBasedFormatRandomNamePrivateStorageAndAudit(): void
    {
        foreach (['png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp'] as $format=>$mime) {
            $photo=$this->upload($format,'<script>alert(1)</script>'); $path=$this->photos->path($photo);
            $this->assertMatchesRegularExpression('~^1/[a-f0-9]{64}\\.' . $format . '$~',$photo['arquivo_anx']);
            $this->assertSame($mime,$photo['mime_anx']); $this->assertSame('3',(string)$photo['usuario_anx']);
            $this->assertSame(filesize($path),(int)$photo['tamanho_anx']);
            $this->assertSame(0600,fileperms($path)&0777); $this->assertSame(0700,fileperms(dirname($path))&0777);
            $this->assertSame(hash_file('sha256',SUPPORTPATH . 'fixtures/photos/valid.' . $format),hash_file('sha256',$path));
        }
        $this->assertSame(3,$this->db->table('tbl_os_historico')->where('evento_osh','foto_adicionada')->where('usuario_osh',3)->countAllResults());
        $body=$this->requestAs(3,'GET','os/1')->getBody(); $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;',$body); $this->assertStringNotContainsString('<script>alert(1)</script>',$body); $this->assertStringNotContainsString('uploads/os-fotos',$body);
    }
    public function testDownloadScopeRolesHeadersAndNoPublicPath(): void
    {
        $photo=$this->upload(); $id=$photo['id_anx'];
        foreach ([1,2,3] as $actor) {
            $r=$this->requestAs($actor,'GET',"os/1/fotos/$id"); $r->assertStatus(200);
            $r->assertHeader('Content-Type','image/png'); $r->assertHeader('X-Content-Type-Options','nosniff'); $this->assertStringContainsString('no-store',$r->response()->getHeaderLine('Cache-Control')); $this->assertStringContainsString('private',$r->response()->getHeaderLine('Cache-Control'));
            $this->assertStringContainsString('inline;',$r->response()->getHeaderLine('Content-Disposition'));
        }
        $this->requestAs(null,'GET',"os/1/fotos/$id")->assertRedirectTo(site_url('login'));
        $this->requestAs(4,'GET',"os/1/fotos/$id")->assertStatus(403);
        $this->requestAs(3,'GET',"os/2/fotos/$id")->assertStatus(404);
        $this->requestAs(3,'GET','os/1/fotos/999')->assertStatus(404);
        $this->requestAs(3,'GET','os/999/fotos/1')->assertStatus(404);
        $this->requestAs(3,'GET',"os/1/fotos/$id",[],true,time()-7201)->assertRedirectTo(site_url('login'));
        foreach (['atribuida','encerrada','cancelada'] as $status) { $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>$status]); $this->requestAs(3,'GET',"os/1/fotos/$id")->assertStatus(200); }
    }
    public function testMissingErroredNonHttpAndSpoofedFilesAreRejected(): void
    {
        $this->denied(fn()=> $this->photos->upload(1,null,'',3),'foto');
        $this->denied(fn()=> $this->photos->upload(1,$this->image('png',UPLOAD_ERR_PARTIAL),'',3),'foto');
        $local=$this->image(); $nonHttp=new UploadedFile($local->getTempName(),'foto.png','image/png',100,UPLOAD_ERR_OK);
        $this->denied(fn()=> $this->photos->upload(1,$nonHttp,'',3),'foto');
        foreach (['<?php echo "executar";','<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>','GIF89a',''] as $bytes) {
            $file=$this->image();file_put_contents($file->getTempName(),$bytes); $this->denied(fn()=> $this->photos->upload(1,$file,'',3),'foto');
        }
        $this->assertSame(0,$this->db->table('tbl_anexo')->countAllResults());
    }
    public function testActualSizeAndDescriptionValidation(): void
    {
        $file=$this->image();$stream=fopen($file->getTempName(),'a');ftruncate($stream,FotoService::MAX_BYTES+1);fclose($stream);
        $this->denied(fn()=> $this->photos->upload(1,$file,'',3),'foto');
        foreach ([['descricao'],str_repeat('x',256)] as $note) { $this->denied(fn()=> $this->photos->upload(1,$this->image(),$note,3),'descricao_foto'); }
        $this->assertSame(0,$this->db->table('tbl_anexo')->countAllResults());
    }
    public function testThirtyActivePhotosLimitAndRemovalFreesSlot(): void
    {
        $first=$this->upload();
        for ($i=0;$i<29;$i++) { $this->db->table('tbl_anexo')->insert(['ordem_servico_anx'=>1,'arquivo_anx'=>'1/' . bin2hex(random_bytes(32)) . '.png','tipo_anx'=>'foto','usuario_anx'=>3,'mime_anx'=>'image/png','tamanho_anx'=>1]); }
        $this->denied(fn()=> $this->photos->upload(1,$this->image(),'',3),'foto');
        $this->photos->remove(1,(int)$first['id_anx'],'Foto repetida',3);
        $this->upload(); $this->assertCount(30,(new AnexoModel($this->db))->forOrder(1));
        $this->assertSame(31,$this->db->table('tbl_anexo')->countAllResults());
    }
    public function testSoftRemovalPreservesFileUploaderAndHistoricalEvidence(): void
    {
        $photo=$this->upload();$id=$photo['id_anx'];$path=$this->photos->path($photo);
        $this->requestAs(2,'POST',"os/1/fotos/$id/remover",['motivo_foto'=>'Operador'])->assertStatus(403);
        $this->requestAs(4,'POST',"os/1/fotos/$id/remover",['motivo_foto'=>'Outro'])->assertStatus(403);
        foreach (['',str_repeat('x',256),['motivo']] as $reason) { $this->requestAs(3,'POST',"os/1/fotos/$id/remover",['motivo_foto'=>$reason])->assertStatus(422); }
        $this->requestAs(1,'POST',"os/2/fotos/$id/remover",['motivo_foto'=>'Aninhamento'])->assertStatus(404);
        $this->requestAs(1,'POST',"os/1/fotos/$id/remover",['motivo_foto'=>'Removida pelo Gestor'])->assertStatus(303);
        $row=$this->db->table('tbl_anexo')->where('id_anx',$id)->get()->getRowArray();
        $this->assertNotNull($row['data_exclusao_anx']); $this->assertSame('3',(string)$row['usuario_anx']); $this->assertFileExists($path);
        $this->requestAs(3,'GET',"os/1/fotos/$id")->assertStatus(404);
        $this->requestAs(1,'POST',"os/1/fotos/$id/remover",['motivo_foto'=>'Repetida'])->assertStatus(404);
        $event=$this->db->table('tbl_os_historico')->where('evento_osh','foto_removida')->get()->getRowArray();$this->assertSame('1',(string)$event['usuario_osh']);$this->assertSame((int)$id,json_decode($event['dados_osh'],true)['foto']);
    }
    public function testOnlyOriginalElectricianRemovesBeforeFinalAndFinalEvidenceIsReadOnly(): void
    {
        $photo=$this->upload();$id=(int)$photo['id_anx'];
        $this->db->table('tbl_anexo')->where('id_anx',$id)->update(['usuario_anx'=>1]);
        $this->requestAs(3,'POST',"os/1/fotos/$id/remover",['motivo_foto'=>'Autoria alheia'])->assertStatus(403);
        foreach (['encerrada','cancelada'] as $status) {
            $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>$status]);
            foreach ([1,3] as $actor) { $this->requestAs($actor,'POST',"os/1/fotos/$id/remover",['motivo_foto'=>'Após final'])->assertStatus(422); }
            $this->denied(fn()=> $this->photos->upload(1,$this->image(),'',3));
            $body=$this->requestAs(3,'GET','os/1')->getBody();$this->assertStringNotContainsString('action="'.site_url('os/1/fotos').'"',$body);$this->assertStringNotContainsString('action="'.site_url("os/1/fotos/$id/remover").'"',$body);
        }
        $this->assertNull($this->db->table('tbl_anexo')->where('id_anx',$id)->get()->getRow()->data_exclusao_anx);
    }
    public function testStorageAndHistoricFailureCompensateUploadedFile(): void
    {
        $before=glob($this->storage . '/1/*') ?: [];
        $file=$this->image(); $file->failMove=true; $this->denied(fn()=> $this->photos->upload(1,$file,'',3),'foto');
        foreach ([['tbl_anexo','test_photo_insert',"tipo_anx <> 'foto'"],['tbl_os_historico','test_photo_history',"evento_osh <> 'foto_adicionada'"]] as [$table,$name,$rule]) {
            $this->db->query("ALTER TABLE $table ADD CONSTRAINT $name CHECK ($rule)");
            $this->denied(fn()=> $this->photos->upload(1,$this->image(),'',3));
            $this->assertSame($before,glob($this->storage . '/1/*') ?: []);$this->assertSame(0,$this->db->table('tbl_anexo')->countAllResults());
            $this->db->query("ALTER TABLE $table DROP CHECK $name");
        }
    }
    public function testFailedRemovalHistoryRollsBackSoftDelete(): void
    {
        $photo=$this->upload();$id=(int)$photo['id_anx'];
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT test_photo_removal CHECK (evento_osh <> 'foto_removida')");
        $this->requestAs(3,'POST',"os/1/fotos/$id/remover",['motivo_foto'=>'Teste rollback'])->assertStatus(422);
        $this->assertNull($this->db->table('tbl_anexo')->where('id_anx',$id)->get()->getRow()->data_exclusao_anx);$this->assertFileExists($this->photos->path($photo));
        $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK test_photo_removal');
    }
    public function testPathTraversalMissingFilesAndSymlinksAreNotServed(): void
    {
        $photo=$this->upload();$id=$photo['id_anx'];$path=$this->photos->path($photo);
        foreach (['../.env','1/../../../.env','2/' . basename($path),'1/test.php'] as $relative) {
            $this->db->table('tbl_anexo')->where('id_anx',$id)->update(['arquivo_anx'=>$relative]);$this->requestAs(3,'GET',"os/1/fotos/$id")->assertStatus(404);
        }
        $this->db->table('tbl_anexo')->where('id_anx',$id)->update(['arquivo_anx'=>$photo['arquivo_anx']]);
        unlink($path);$target=$this->image()->getTempName();symlink($target,$path);$this->requestAs(3,'GET',"os/1/fotos/$id")->assertStatus(404);
        unlink($path);$this->requestAs(3,'GET',"os/1/fotos/$id")->assertStatus(404);
    }
    public function testStorageFailureDoesNotExposePathsOrSaveMetadata(): void
    {
        $blocked=tempnam('/tmp','gpm-photo-blocked-'); $this->files[]=$blocked;
        $service=new FotoService($this->db,$blocked);
        $this->denied(fn()=> $service->upload(1,$this->image(),'',3),'foto');
        $public=FCPATH . 'gpm-photo-private-test-' . bin2hex(random_bytes(8));
        try { $this->denied(fn()=> (new FotoService($this->db,$public))->upload(1,$this->image(),'',3),'foto'); }
        finally { if (is_dir($public . '/1')) { rmdir($public . '/1'); } if (is_dir($public)) { rmdir($public); } }
        $this->assertSame(0,$this->db->table('tbl_anexo')->countAllResults());
    }

    public function testInactiveUserAndDeletedOrderCannotReadOrChangePhotos(): void
    {
        $photo=$this->upload(); $id=$photo['id_anx'];
        $this->db->table('tbl_usuario')->where('id_usu',3)->update(['ativo_usu'=>0]);
        $this->requestAs(3,'GET',"os/1/fotos/$id")->assertRedirectTo(site_url('login'));
        $this->requestAs(3,'POST',"os/1/fotos/$id/remover",['motivo_foto'=>'Inativo'])->assertRedirectTo(site_url('login'));
        $this->db->table('tbl_usuario')->where('id_usu',3)->update(['ativo_usu'=>1]);
        $this->db->table('tbl_os')->where('id_oss',1)->update(['data_exclusao_oss'=>date('Y-m-d H:i:s')]);
        foreach ([1,2,3] as $actor) { $this->requestAs($actor,'GET',"os/1/fotos/$id")->assertStatus(404); }
        $this->requestAs(3,'POST',"os/1/fotos/$id/remover",['motivo_foto'=>'OS excluída'])->assertStatus(404);
        $this->assertNull($this->db->table('tbl_anexo')->where('id_anx',$id)->get()->getRow()->data_exclusao_anx);
    }

    public function testServicesRecheckActorRoleAndStatus(): void
    {
        $this->db->table('tbl_usuario')->where('id_usu',3)->update(['ativo_usu'=>0]);
        foreach ([1,2,3,4] as $actor) { $this->denied(fn()=> $this->photos->upload(1,$this->image(),'', $actor)); }
        $this->db->table('tbl_usuario')->where('id_usu',3)->update(['ativo_usu'=>1]);
        foreach (['aberta','atribuida'] as $status) { $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>$status]);$this->denied(fn()=> $this->photos->upload(1,$this->image(),'',3)); }
    }
}
