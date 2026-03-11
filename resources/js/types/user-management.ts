export type ManagedUserRole = {
  id: number;
  name: string;
  display_name: string;
};

export type ManagedUserListItem = {
  id: number;
  name: string;
  email: string;
  active: boolean;
  email_verified_at: string | null;
  roles: ManagedUserRole[];
};

export type ManagedUserFormValues = {
  name: string;
  email: string;
  active: boolean;
  roles: number[];
};

export type ManagedUserEditingTarget = ManagedUserListItem & ManagedUserFormValues;

export type ManagedUserRoleOption = {
  id: number;
  name: string;
  display_name: string;
  is_system: boolean;
};

export type UsersIndexPageProps = {
  users: ManagedUserListItem[];
  filters: {
    search: string;
    role: string;
    status: 'all' | 'active' | 'inactive';
  };
  stats: {
    total: number;
    active: number;
    inactive: number;
  };
  roles: ManagedUserRoleOption[];
  status?: string;
  error?: string;
};

export type UserEditPageProps = {
  user: ManagedUserEditingTarget;
  initialValues: ManagedUserFormValues;
  availableRoles: ManagedUserRoleOption[];
};
