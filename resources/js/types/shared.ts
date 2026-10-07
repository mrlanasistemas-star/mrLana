/**
 * Props compartidas por Inertia en todas las páginas (HandleInertiaRequests).
 */
export type AuthUser = {
    id: number
    name: string
    email: string
    empleado_id: number | null
    activo: boolean
    email_verified_at: string | null
    roles: string[]
}

export type AppSettings = {
    colors: {
        primary_color: string
        accent_color: string
        button_color: string
        success_color: string
        warning_color: string
        danger_color: string
    }
    customized: Partial<Record<keyof AppSettings['colors'], boolean>>
    chart_palette: string[]
    mobile_app_url: string | null
    logo_url: string | null
}

export type FlashProps = {
    success?: string | null
    error?: string | null
    warning?: string | null
    folio_created_id?: number | null
    folio_updated_id?: number | null
}

export type SharedProps = {
    auth: {
        user: AuthUser | null
        permissions: string[]
    }
    notifications: { unread_count: number } | null
    appSettings: AppSettings
    flash: FlashProps
    errors: Record<string, string>
}

export type Paginated<T> = {
    data: T[]
    links: { url: string | null; label: string; active: boolean }[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
}

export type ErpNotificationItem = {
    id: string
    title: string
    message: string
    category: string
    category_label: string
    severity: 'info' | 'success' | 'warning' | 'danger'
    url: string | null
    read_at: string | null
    created_at: string | null
}
