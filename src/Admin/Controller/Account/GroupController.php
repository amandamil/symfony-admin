<?php

/**
 * This file is part of the pdAdmin package.
 *
 * @package     pdAdmin
 *
 * @author      Ramazan APAYDIN <iletisim@ramazanapaydin.com>
 * @copyright   Copyright (c) 2018 pdAdmin
 * @license     LICENSE
 *
 * @link        http://pdadmin.ramazanapaydin.com
 */

namespace App\Admin\Controller\Account;

use App\Admin\Entity\Account\Group;
use App\Admin\Services\Security;
use Pd\UserBundle\Form\GroupType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller managing the groups.
 *
 * @author  Ramazan Apaydın <iletisim@ramazanapaydin.com>
 */
class GroupController extends Controller
{
    /**
     * List Group.
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @IsGranted("ADMIN_GROUP_LIST")
     */
    public function list(Request $request)
    {
        // Get Groups
        $query = $this->getDoctrine()->getRepository(Group::class)
            ->createQueryBuilder('g');

        // Get Result
        $pagination = $this->get('knp_paginator');
        $pagination = $pagination->paginate(
            $query,
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', $this->getParameter('list_count'))
        );

        // Set Back URL
        $this->get('session')->set('backUrl', $request->getRequestUri());

        // Render
        return $this->render('@Admin/Account/Groups/list.html.twig', [
            'groups' => $pagination,
        ]);
    }

    /**
     * Edit Group.
     *
     * @param Group   $group
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @IsGranted("ADMIN_GROUP_EDIT")
     */
    public function edit(Group $group, Request $request)
    {
        // Create Form
        $form = $this->createForm(GroupType::class, $group, [
            'data_class' => Group::class
        ]);

        // Handle Request
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save
            $em = $this->getDoctrine()->getManager();
            $em->persist($group);
            $em->flush();

            // Message
            $this->addFlash('success', 'changes_saved');
        }

        return $this->render('@Admin/Account/Groups/edit.html.twig', [
            'form' => $form->createview(),
            'group' => $group,
        ]);
    }

    /**
     * Add New Group.
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @IsGranted("ADMIN_GROUP_NEW")
     */
    public function new(Request $request)
    {
        // Create Form
        $group = new Group(null);
        $form = $this->createForm(GroupType::class, $group);

        // Handle Request
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save
            $em = $this->getDoctrine()->getManager();
            $em->persist($group);
            $em->flush();

            // Success Messagae
            $this->addFlash('success', 'changes_saved');

            // Redirect Edit
            return $this->redirectToRoute('admin_account_group_edit', ['group' => $group->getId()]);
        }

        return $this->render('@Admin/Account/Groups/new.html.twig', [
            'form' => $form->createview(),
        ]);
    }

    /**
     * Edit Group Roles.
     *
     * @param Group   $group
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @IsGranted("ADMIN_GROUP_ROLES")
     */
    public function roles(Group $group, Request $request)
    {
        // All Roles
        $security = new Security($this->container);
        $roles = $security->getRoles();

        // Create Form
        $ACL = $security->getACL();
        $form = $this->createFormBuilder([])
            ->add('ACL', ChoiceType::class, [
                'label' => false,
                'multiple' => false,
                'expanded' => true,
                'choices' => $ACL,
                'choice_label' => function ($value, $key, $index) {
                    return $key.'.title';
                },
                'data' => key(array_intersect($ACL, $group->getRoles())),
            ])
            ->add('ACLProcess', ChoiceType::class, [
                'label' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'ROLE_ALLOWED_TO_SWITCH.title' => 'ROLE_ALLOWED_TO_SWITCH',
                ],
                'data' => $group->getRoles(),
                'required' => false,
            ])
            ->add('Submit', SubmitType::class, [
                'label' => 'save',
                'attr' => ['class' => 'btn-primary'],
            ]);

        //Add Form Items
        if (count($roles)) {
            foreach ($roles as $role => $access) {
                $form->add($role, ChoiceType::class, [
                    'label' => false,
                    'multiple' => true,
                    'expanded' => true,
                    'choices' => $access,
                    'choice_label' => function ($value, $key, $index) use ($role) {
                        return $role.'.'.$key;
                    },
                    'data' => $group->getRoles(),
                ]);
            }
        }

        // Set Form & Request
        $form = $form->getForm();
        $form->handleRequest($request);

        // Valid Form
        if ($form->isSubmitted() && $form->isValid()) {
            // Group Add Roles
            $addRoles = [];
            foreach ($form->getData() as $roleName => $roles) {
                if ($roles) {
                    if (!is_array($roles)) {
                        $roles = [$roles];
                    }
                    // Add Role Group
                    if ('ACL' !== $roleName && 'ACLProcess' !== $roleName) {
                        array_push($roles, $roleName);
                    }
                    $addRoles = array_merge($addRoles, $roles);
                }
            }
            $group->setRoles($addRoles);

            // Save
            $em = $this->getDoctrine()->getManager();
            $em->persist($group);
            $em->flush();

            // Message
            $this->addFlash('success', 'changes_saved');
        }

        // Return
        return $this->render('@Admin/Account/Groups/roles.html.twig', [
            'form' => $form->createView(),
            'group' => $group,
            'roles' => $roles,
        ]);
    }

    /**
     * Delete Group.
     *
     * @param Group   $group
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @IsGranted("ADMIN_GROUP_DELETE")
     */
    public function delete(Group $group, Request $request)
    {
        // Remove
        $em = $this->getDoctrine()->getManager();
        $em->remove($group);
        $em->flush();

        // Add Flash
        $this->addFlash('success', 'group_deleted');

        // Redirect back
        return $this->redirect(($r = $request->headers->get('referer')) ? $r : $this->generateUrl('admin_account_group_list'));
    }
}
