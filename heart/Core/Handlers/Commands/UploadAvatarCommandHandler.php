<?php namespace Qdiscuss\Core\Handlers\Commands;

use Qdiscuss\Core\Commands\UploadAvatarCommand;
use Qdiscuss\Core\Events\AvatarWillBeUploaded;
use Qdiscuss\Core\Repositories\UserRepositoryInterface;
use Qdiscuss\Core\Support\DispatchesEvents;
use Illuminate\Support\Str;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\MountManager;

class UploadAvatarCommandHandler
{
    use DispatchesEvents;

    /**
     * @var UserRepositoryInterface
     */
    protected $users;

    /**
     * @var FilesystemInterface
     */
    protected $uploadDir;

    public function __construct(UserRepositoryInterface $users, FilesystemInterface $uploadDir)
    {
        $this->users = $users;
        $this->uploadDir = $uploadDir;
    }

    public function handle(UploadAvatarCommand $command)
    {
        
        $user = $this->users->findOrFail($command->userId);

        // Make sure the current user is allowed to edit the user profile.
        // This will let admins and the user themselves pass through, and
        // throw an exception otherwise.
        $user->assertCan($command->actor, 'edit');

        $filename = $command->file->getFilename();
        $uploadName = Str::lower(Str::quickRandom()) . '.jpg';

        $mount = new MountManager([
            'source' => new Filesystem(new Local($command->file->getPath())),
            'target' => $this->uploadDir,
        ]);


       if ($user->avatar_path && $mount->has($file = "target://$user->avatar_path")) {
            $mount->delete($file);
        }

        $user->changeAvatarPath($uploadName);

        event(new AvatarWillBeUploaded($user, $command));

        $mount->move("source://$filename", "target://$uploadName");
        $user->save();
        $this->dispatchEventsFor($user);

        return $user;
    }
}
