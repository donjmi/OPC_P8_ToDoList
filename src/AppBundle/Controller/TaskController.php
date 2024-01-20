<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Form\TaskType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TaskController extends Controller
{
    /**
     * @Route("/tasks", name="task_list")
     */
    public function listAction()
    {
        return $this->render('task/list.html.twig', ['tasks' => $this->getDoctrine()->getRepository('AppBundle:Task')->findAll()]);
    }

    /**
     * @Route("/tasksTab", name="task_tab")
     */
    public function tabAction()
    {
        return $this->render('task/tab.html.twig', ['tasks' => $this->getDoctrine()->getRepository('AppBundle:Task')->findAll()]);
    }

    /**
     * @Route("/tasks/create", name="task_create")
     */
    public function createAction(Request $request)
    {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            //add user create
            $task->setUser($this->getUser());

            $em->persist($task);
            $em->flush();

            $this->addFlash('success', 'La tâche a été bien été ajoutée.');

            return $this->redirectToRoute('task_list');
        }

        return $this->render('task/create.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/tasks/{id}/edit", name="task_edit")
     */
    public function editAction(Task $task, Request $request)
    {
        $form = $this->createForm(TaskType::class, $task);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash('success', 'La tâche a bien été modifiée.');

            return $this->redirectToRoute('task_list');
        }

        return $this->render('task/edit.html.twig', [
            'form' => $form->createView(),
            'task' => $task,
        ]);
    }

    /**
     * @Route("/tasks/{id}/toggle", name="task_toggle")
     */
    public function toggleTaskAction(Task $task)
    {
        $task->toggle(!$task->isDone());
        $this->getDoctrine()->getManager()->flush();

        $this->addFlash('success', sprintf('La tâche %s a bien été marquée comme faite.', $task->getTitle()));

        return $this->redirectToRoute('task_list');
    }

    /**
    * @Route("/tasks/{id}/delete", name="task_delete")
    */
    public function deleteTaskAction(Task $task)
    {
        $currentUser = $this->getUser();

        // Vérifier si l'utilisateur actuel a le droit de supprimer la tâche
        if (!(
            // L'utilisateur peut supprimer sa propre tâche sauf si elle est associée à l'utilisateur anonyme
            ($task->getUser() === $currentUser && $currentUser !== null && $currentUser->getUsername() !== 'anonymous') ||
            // Les utilisateurs avec le rôle 'ROLE_ADMIN' peuvent supprimer toutes les tâches, y compris celles associées à l'utilisateur anonyme
            ($this->isGranted('ROLE_ADMIN') && $task->getUser() === null)
        )) {
            throw new AccessDeniedException("Vous n'avez pas les autorisations nécessaires pour supprimer cette tâche.");
        }
    
        
        $em = $this->getDoctrine()->getManager();
        $em->remove($task);
        $em->flush();

        $this->addFlash('success', 'La tâche a bien été supprimée.');

        return $this->redirectToRoute('task_list');
    }

    /**
     * @Route("/tasks/assignations", name="assign_user_tache")
    */
    public function assignAnonymousUserToTasksAction()
    {
        // Vérification que l'utilisateur est "ROLE_ADMIN"
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException("Vous n'avez pas les autorisations nécessaires pour effectuer cette opération.");
        }

        $em = $this->getDoctrine()->getManager();
        $tasksWithoutUser = $em->getRepository(Task::class)->findBy(['user' => null]);

        foreach ($tasksWithoutUser as $task) {
            // Récupération de l'utilisateur "anonyme"
            $anonymousUser = $em->getRepository(User::class)->findOneBy(['username' => 'anonyme']);

            if ($anonymousUser) {
                $task->setUser($anonymousUser);
                $em->persist($task);
            }
        }

        $em->flush();

        $this->addFlash('success', 'Les tâches ont été attribuées à l\'utilisateur "anonyme".');

        return $this->redirectToRoute('task_list');
    }
}
