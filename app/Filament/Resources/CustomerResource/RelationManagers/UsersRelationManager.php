<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\User;
use App\Services\CustomerMembershipManager;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'کاربران دارای دسترسی';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable(),
                Tables\Columns\TextColumn::make('mobile')->label('موبایل')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('ایمیل')->placeholder('—'),
                Tables\Columns\TextColumn::make('membership_role')
                    ->label('نقش عضویت')
                    ->state(fn (User $record): string => $record->pivot->membership_role)
                    ->formatStateUsing(fn (string $state): string => self::roleOptions()[$state] ?? $state),
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('مخاطب اصلی')
                    ->state(fn (User $record): bool => (bool) $record->pivot->is_primary)
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('assignClient')
                    ->label('افزودن کاربر سایت')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('کاربر')
                            ->options(fn (): array => User::query()
                                ->where('is_admin', false)
                                ->where('status', 'active')
                                ->whereNotIn('id', $this->getOwnerRecord()->users()->select('users.id'))
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [
                                    $user->id => $user->name.' — '.$user->mobile,
                                ])->all())
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('membership_role')
                            ->label('نقش عضویت')
                            ->options(self::roleOptions())
                            ->default('member')
                            ->required(),
                        Forms\Components\Toggle::make('is_primary')->label('مخاطب اصلی'),
                    ])
                    ->action(function (array $data, CustomerMembershipManager $memberships): void {
                        $user = User::query()->find($data['user_id']);

                        abort_unless($user, 404);
                        $memberships->attach(
                            $this->getOwnerRecord(),
                            $user,
                            $data['membership_role'],
                            (bool) ($data['is_primary'] ?? false),
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('editMembership')
                    ->label('ویرایش عضویت')
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (User $record): array => [
                        'membership_role' => $record->pivot->membership_role,
                        'is_primary' => (bool) $record->pivot->is_primary,
                    ])
                    ->form([
                        Forms\Components\Select::make('membership_role')
                            ->label('نقش عضویت')
                            ->options(self::roleOptions())
                            ->required(),
                        Forms\Components\Toggle::make('is_primary')->label('مخاطب اصلی'),
                    ])
                    ->action(function (User $record, array $data, CustomerMembershipManager $memberships): void {
                        $memberships->assign(
                            $this->getOwnerRecord(),
                            $record,
                            $data['membership_role'],
                            (bool) ($data['is_primary'] ?? false),
                        );
                    }),
                Tables\Actions\DetachAction::make()
                    ->label('حذف عضویت')
                    ->modalHeading('حذف عضویت کاربر')
                    ->modalDescription('حساب کاربری حذف نمی‌شود؛ فقط دسترسی آن به این مشتری قطع خواهد شد.'),
            ]);
    }

    private static function roleOptions(): array
    {
        return ['owner' => 'مالک', 'member' => 'عضو'];
    }
}
