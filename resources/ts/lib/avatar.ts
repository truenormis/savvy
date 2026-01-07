interface UserLike {
    email: string
    name?: string
}

export function getUserAvatarUrl(user: UserLike): string {
    return `https://api.dicebear.com/9.x/identicon/png?seed=${encodeURIComponent(user.email)}&backgroundColor=b6e3f4,c0aede,d1d4f9`
}

export function getUserInitials(user: UserLike): string {
    return user.name?.charAt(0).toUpperCase() ?? user.email.charAt(0).toUpperCase()
}
