<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sistema de Gerenciamento de Tarefas - Mensagens de Validação em Português
    |--------------------------------------------------------------------------
    */

    'task_creation' => [
        'title.required' => 'O título da tarefa é obrigatório e não pode estar vazio.',
        'title.string' => 'O título da tarefa deve ser uma string de texto válida.',
        'title.min' => 'O título da tarefa deve ter pelo menos 1 caractere.',
        'title.max' => 'O título da tarefa não pode exceder 255 caracteres.',
        'title.regex' => 'O título da tarefa contém caracteres inválidos. Apenas letras, números, espaços e pontuação comum (.,!?-_) são permitidos.',
        
        'description.string' => 'A descrição da tarefa deve ser uma string de texto válida.',
        'description.max' => 'A descrição da tarefa não pode exceder 1.000 caracteres.',
        
        'status.string' => 'O status deve ser um valor de texto válido.',
        'status.in' => 'O status selecionado é inválido. Escolha entre: :values',
        
        'created_by.integer' => 'O ID do criador deve ser um número válido.',
        'created_by.min' => 'O ID do criador deve ser um número positivo maior que 0.',
        
        'assigned_to.integer' => 'O ID do responsável deve ser um número válido.',
        'assigned_to.min' => 'O ID do responsável deve ser um número positivo maior que 0.',
        
        'due_date.date' => 'A data de vencimento deve ser uma data válida.',
        'due_date.after' => 'A data de vencimento deve ser no futuro.',
        'due_date.before' => 'A data de vencimento não pode ser mais de 10 anos no futuro.',
        
        'priority.string' => 'A prioridade deve ser um valor de texto válido.',
        'priority.in' => 'A prioridade deve ser: baixa, média ou alta.',
    ],

    'business_rules' => [
        'status_transition.invalid' => 'Transição de status inválida de ":from" para ":to".',
        'status_transition.completed_requires_completion_date' => 'Marcar uma tarefa como concluída requer definir uma data de conclusão.',
        'status_transition.cannot_reopen_completed' => 'Tarefas concluídas não podem ser reabertas. Crie uma nova tarefa em vez disso.',
        
        'assignment.self_assignment' => 'Você não pode atribuir uma tarefa para si mesmo como criador e responsável.',
        'assignment.invalid_user' => 'O ID do usuário especificado não existe ou está inativo.',
        
        'due_date.overdue_completion' => 'Não é possível marcar uma tarefa atrasada como concluída sem reconhecer o atraso.',
        'due_date.past_due_update' => 'Não é possível definir uma data de vencimento no passado para tarefas ativas.',
        
        'task_deletion.has_dependencies' => 'Esta tarefa não pode ser excluída porque outras tarefas dependem dela.',
        'task_deletion.already_completed' => 'Tarefas concluídas não podem ser excluídas, apenas arquivadas.',
        
        'priority.escalation_required' => 'Tarefas de alta prioridade requerem aprovação do gerente para mudanças de atribuição.',
    ],

    'context' => [
        'task_not_found' => 'A tarefa solicitada (ID: :id) não foi encontrada ou pode ter sido excluída.',
        'user_not_found' => 'O usuário especificado (ID: :id) não existe no sistema.',
        'validation_failed' => 'Os dados enviados contêm erros. Revise e corrija os campos destacados.',
        'operation_failed' => 'A operação solicitada não pôde ser concluída devido a restrições do sistema.',
        'permission_denied' => 'Você não tem permissões suficientes para executar esta ação.',
        'system_error' => 'Ocorreu um erro do sistema. Tente novamente ou entre em contato com o suporte se o problema persistir.',
    ],

];