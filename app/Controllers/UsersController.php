<?php
namespace App\Controllers;
use App\Helpers\Response;

class UsersController {
    private function ensureMigrate(): void { require_once __DIR__.'/../../lib/db.php'; migrate(); }

    public function index(): void {
        require_once __DIR__.'/../../lib/db.php';
        require_once __DIR__.'/../../partials/flash.php';
        $this->ensureMigrate();
        $users = db()->query('SELECT id, username, notes, created_at FROM users ORDER BY username ASC')->fetchAll();
        Response::view('users/index', compact('users'));
    }

    public function create(): void {
        require_once __DIR__.'/../../partials/flash.php';
        $this->ensureMigrate();
        Response::view('users/create', []);
    }

    public function store(): void {
        require_once __DIR__.'/../../lib/db.php';
        require_once __DIR__.'/../../partials/flash.php';
        $this->ensureMigrate();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';
        $notesRaw = trim((string)($_POST['notes'] ?? ''));
        $notes = $notesRaw === '' ? null : mb_substr($notesRaw, 0, 1000);
        $err=[];
        if(strlen($username)<3) $err[]='Nom trop court (min 3).';
        if($password!==$confirm) $err[]='Confirmation du mot de passe différente.';
        if(strlen($password)<8) $err[]='Mot de passe trop court (min 8).';
        if(!preg_match('~[A-Z]~',$password)) $err[]='Ajouter une majuscule.';
        if(!preg_match('~[a-z]~',$password)) $err[]='Ajouter une minuscule.';
        if(!preg_match('~\\d~',$password)) $err[]='Ajouter un chiffre.';
        $st=db()->prepare('SELECT COUNT(*) FROM users WHERE username=:u COLLATE NOCASE'); $st->execute([':u'=>$username]);
        if($st->fetchColumn()>0) $err[]='Nom déjà pris.';
        if(!$err){
            $st=db()->prepare('INSERT INTO users(username,password_hash,notes,created_at) VALUES(:u,:p,:n,:c)');
            $st->execute([':u'=>$username, ':p'=>password_hash($password,PASSWORD_BCRYPT), ':n'=>$notes, ':c'=>date('c')]);
            require_once __DIR__.'/../../lib/auth.php';
            audit('user.create',['username'=>$username, 'notes'=>$notes]);
            flash('ok','Utilisateur créé.');
            Response::redirect('/users');
        } else {
            flash('err', implode(' ', $err));
            Response::redirect('/users/new');
        }
    }

    public function edit(): void {
        require_once __DIR__.'/../../lib/db.php';
        require_once __DIR__.'/../../partials/flash.php';
        $this->ensureMigrate();
        $id = (int)($_GET['id'] ?? 0);
        $st = db()->prepare('SELECT * FROM users WHERE id=:id'); $st->execute([':id'=>$id]); $user=$st->fetch();
        if(!$user){ http_response_code(404); echo 'Utilisateur introuvable'; return; }
        Response::view('users/edit', compact('user'));
    }

    public function update(): void {
        require_once __DIR__.'/../../lib/db.php';
        require_once __DIR__.'/../../partials/flash.php';
        $this->ensureMigrate();
        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        $st = db()->prepare('SELECT * FROM users WHERE id=:id'); $st->execute([':id'=>$id]); $user=$st->fetch();
        if(!$user){ http_response_code(404); echo 'Utilisateur introuvable'; return; }
        $username = trim($_POST['username'] ?? '');
        $notesRaw = trim((string)($_POST['notes'] ?? ''));
        $notes = $notesRaw === '' ? null : mb_substr($notesRaw, 0, 1000);
        $err=[];
        if(strlen($username)<3) $err[]='Nom trop court (min 3).';
        if($username!==$user['username']){
            $chk=db()->prepare('SELECT COUNT(*) FROM users WHERE username=:u COLLATE NOCASE AND id<>:id'); $chk->execute([':u'=>$username, ':id'=>$id]);
            if($chk->fetchColumn()>0) $err[]='Nom déjà pris.';
        }
        if(!$err){
            db()->prepare('UPDATE users SET username=:u, notes=:n WHERE id=:id')->execute([':u'=>$username, ':n'=>$notes, ':id'=>$id]);
            require_once __DIR__.'/../../lib/auth.php';
            audit('user.update',[ 'from'=>$user['username'], 'to'=>$username, 'notes_before'=>$user['notes'] ?? null, 'notes_after'=>$notes ]);
            if($user['username']===current_user()) $_SESSION['user']=$username;
            flash('ok','Utilisateur mis à jour.');
            Response::redirect('/users');
        } else { flash('err', implode(' ', $err)); Response::redirect('/users/'.$id.'/edit'); }
    }

    public function resetPassword(): void {
        require_once __DIR__.'/../../lib/db.php';
        require_once __DIR__.'/../../partials/flash.php';
        $this->ensureMigrate();
        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        $st = db()->prepare('SELECT * FROM users WHERE id=:id'); $st->execute([':id'=>$id]); $user=$st->fetch();
        if(!$user){ http_response_code(404); echo 'Utilisateur introuvable'; return; }
        $p1=$_POST['password']??''; $p2=$_POST['confirm']??'';
        $err=[];
        if($p1!==$p2) $err[]='Confirmation différente.';
        if(strlen($p1)<8) $err[]='Mot de passe trop court (min 8).';
        if(!preg_match('~[A-Z]~',$p1)) $err[]='Ajouter une majuscule.';
        if(!preg_match('~[a-z]~',$p1)) $err[]='Ajouter une minuscule.';
        if(!preg_match('~\\d~',$p1)) $err[]='Ajouter un chiffre.';
        if(!$err){
            db()->prepare('UPDATE users SET password_hash=:h WHERE id=:id')->execute([':h'=>password_hash($p1,PASSWORD_BCRYPT), ':id'=>$id]);
            require_once __DIR__.'/../../lib/auth.php';
            audit('user.password.reset',[ 'username'=>$user['username'] ]);
            flash('ok','Mot de passe réinitialisé.');
            Response::redirect('/users');
        } else { flash('err', implode(' ', $err)); Response::redirect('/users/'.$id.'/edit'); }
    }

    public function destroy(): void {
        require_once __DIR__.'/../../lib/db.php';
        require_once __DIR__.'/../../partials/flash.php';
        $this->ensureMigrate();
        $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
        $st = db()->prepare('SELECT username FROM users WHERE id=:id'); $st->execute([':id'=>$id]); $row=$st->fetch();
        if($row){
            require_once __DIR__.'/../../lib/auth.php';
            if($row['username']===current_user()){
                flash('err','Impossible de supprimer votre propre compte.');
            } else {
                $c = (int)db()->query('SELECT COUNT(*) AS c FROM users')->fetchColumn();
                if($c<=1){ flash('err','Au moins un utilisateur doit exister.'); }
                else { db()->prepare('DELETE FROM users WHERE id=:id')->execute([':id'=>$id]); audit('user.delete',[ 'username'=>$row['username'] ]); flash('ok','Utilisateur supprimé.'); }
            }
        }
        Response::redirect('/users');
    }

    // Legacy POST /user_edit.php that uses action=save_profile|reset_password
    public function legacyPost(): void {
        $action = $_POST['action'] ?? '';
        if ($action === 'reset_password') { $this->resetPassword(); return; }
        $this->update();
    }
}
