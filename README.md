# Commandes

## Configuration

```
{
    "repositoryDirectory": "",
    "repositoryUrl": "",
    "debug": true
}
```

`~/.config/sync-dot-files.json`

## Initialisation repository distant & config locale (premier pc)

`sync-dot-files.php init-remote`

## Initialisation config local (premier pc)

`sync-dot-files.php init-local`

## Ajout d'un fichier dans le registre

`sync-dot-files.php add ~/fichier.txt`

/!\ Le fichier n'est pas encore sauvegarder, il faudra faire un `push` après

## Suppression d'un fichier dans le registre

`sync-dot-files.php remove ~/fichier.txt`

## Récupérer les données en lignes et dispatcher sur les fichiers locaux

`sync-dot-files.php pull`

**/!\ Cette opération écrase les fichiers locaux, en cas de doute de modification sur les fichiers locaux, il est préférable de faire un push auparavant.**

## Pusher les modifications locales en ligne

`sync-dot-files.php push`

Un commit est toujours effectué en local avant de faire un pull pour pouvoir gerer les conflits.

Si conflit il y a, il faudra merger manuellement les fichiers dans le repo et ensuite faire un `git push`. 
On repercutera les modifs du merge en local en faisant un `sync-dot-files.php pull`