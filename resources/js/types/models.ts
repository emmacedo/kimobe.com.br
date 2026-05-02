export interface User {
    id: number;
    name: string;
    email: string;
    telefone?: string | null;
    tipo_pessoa?: 'pf' | 'pj';
    documento?: string | null;
}

/**
 * Proprietário = Vinculo (papel='proprietario') + dados do User aninhados.
 * É o formato retornado pelos endpoints /proprietarios/* e usado no autocomplete.
 */
export interface Proprietario {
    vinculo_id: number;
    user_id: number;
    name: string;
    email: string | null;
    telefone: string | null;
    tipo_pessoa: 'pf' | 'pj';
    documento: string | null;
    status: 'ativo' | 'inativo' | 'pendente';
    email_placeholder?: boolean;
}

/**
 * Inquilino — mesmo shape do Proprietario mas semanticamente é Vinculo (papel='inquilino').
 * Retornado por /inquilinos/* e /inquilinos/buscar.
 */
export interface Inquilino {
    vinculo_id: number;
    user_id: number;
    name: string;
    email: string | null;
    telefone: string | null;
    tipo_pessoa: 'pf' | 'pj';
    documento: string | null;
    status: 'ativo' | 'inativo' | 'pendente';
    email_placeholder?: boolean;
}

/**
 * Imóvel disponível no autocomplete de novo contrato (sem contrato ativo).
 * Inclui informações dos titulares para exibir contexto na listagem.
 */
export interface ImovelDisponivel {
    id: number;
    logradouro: string;
    numero: string;
    complemento: string | null;
    bairro: string;
    cidade: string;
    uf: string;
    tipo: string;
    valor_aluguel_sugerido: string | null;
    titularidades: Array<{
        vinculo: { user: { name: string } };
        percentual: string;
        papel: string;
    }>;
}

export interface Vinculo {
    id: number;
    user_id: number;
    tenant_id: number;
    papel: 'admin' | 'proprietario' | 'inquilino';
    status: 'ativo' | 'inativo' | 'pendente';
    user: User;
}

export interface ImovelFoto {
    id: number;
    imovel_id: number;
    caminho: string;
    url: string;
    nome_arquivo: string;
    legenda: string | null;
    ordem: number;
    mime_type: string;
    tamanho_bytes: number;
}

export interface DadosBancarios {
    id: number;
    vinculo_id: number;
    apelido: string;
    banco_codigo: string;
    banco_nome: string;
    agencia: string;
    conta: string;
    tipo_conta: 'corrente' | 'poupanca';
    pix_tipo: 'cpf' | 'cnpj' | 'email' | 'telefone' | 'aleatoria' | null;
    pix_chave: string | null;
}

export interface Titularidade {
    id: number;
    imovel_id: number;
    vinculo_id: number;
    dados_bancarios_id: number | null;
    tipo_titular: 'pessoa_fisica' | 'empresa' | 'inventario';
    papel: 'responsavel' | 'observador';
    percentual: string; // decimal vem como string do backend
    vinculo: Vinculo;
    dados_bancarios: DadosBancarios | null;
}

export interface ContratoResponsabilidade {
    id: number;
    contrato_id: number;
    descricao: string;
    responsavel: 'proprietario' | 'inquilino';
    valor: string | null;
    periodicidade: 'mensal' | 'anual' | 'avulso';
    predefinido: boolean;
    observacoes: string | null;
}

export interface Fiador {
    id: number;
    contrato_id: number;
    nome: string;
    cpf: string;
    rg: string | null;
    telefone: string;
    email: string | null;
    profissao: string | null;
    estado_civil: string | null;
    cep: string;
    logradouro: string;
    numero: string;
    complemento: string | null;
    bairro: string;
    cidade: string;
    uf: string;
}

export interface Administradora {
    id: number;
    nome: string;
    cpf_cnpj: string | null;
    telefone: string | null;
    email: string | null;
    site: string | null;
    contato_interno_nome: string | null;
    cep: string | null;
    logradouro: string | null;
    numero: string | null;
    complemento: string | null;
    bairro: string | null;
    cidade: string | null;
    uf: string | null;
    observacoes: string | null;
}

export interface Condominio {
    id: number;
    imovel_id: number;
    administradora_id: number | null;
    dia_vencimento: number | null;
    valor: string | null;
    acesso_login: string | null;
    acesso_senha: string | null;
    acesso_descricao: string | null;
    administradora?: Administradora | null;
}

export interface Imovel {
    id: number;
    cep: string;
    logradouro: string;
    numero: string;
    complemento: string | null;
    bairro: string;
    cidade: string;
    uf: string;
    inscricao_iptu: string | null;
    tipo: string;
    status: string;
    quartos: number | null;
    suites: number | null;
    banheiros: number | null;
    vagas_garagem: number | null;
    andar: number | null;
    area_m2: string | null;
    valor_aluguel_sugerido: string | null;
    observacoes: string | null;
    created_at: string;
    updated_at: string;
    foto_principal: ImovelFoto | null;
    fotos: ImovelFoto[];
    titularidades: Titularidade[];
    condominio?: Condominio | null;
}
