import 'package:flutter/material.dart';
import 'package:moscow_pilgrim/core/app_theme.dart';
import 'package:moscow_pilgrim/core/session_controller.dart';

class AuthScreen extends StatefulWidget {
  const AuthScreen({super.key});

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _register = false;
  bool _obscure = true;
  bool _consent = true;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_register && !_consent) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Нужно согласиться с обработкой персональных данных.')),
      );
      return;
    }

    final session = SessionController.instance;
    final success = _register
        ? await session.register(
            name: _nameController.text,
            email: _emailController.text,
            phone: _phoneController.text,
            password: _passwordController.text,
          )
        : await session.login(
            email: _emailController.text,
            password: _passwordController.text,
          );

    if (!mounted || success) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(session.error ?? 'Не удалось выполнить вход.'),
        backgroundColor: Theme.of(context).colorScheme.error,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionController.instance;
    return Scaffold(
      body: SafeArea(
        child: ListenableBuilder(
          listenable: session,
          builder: (context, _) {
            return Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 480),
                  child: Column(
                    children: [
                      Container(
                        width: 84,
                        height: 84,
                        decoration: const BoxDecoration(
                          color: AppTheme.gold,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.church_rounded, size: 44, color: Colors.white),
                      ),
                      const SizedBox(height: 22),
                      Text(
                        'Московский паломник',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                              color: AppTheme.green,
                            ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _register
                            ? 'Создайте профиль паломника'
                            : 'Войдите, чтобы сохранять маршруты, билеты и достижения',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant),
                      ),
                      const SizedBox(height: 28),
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(22),
                          child: Form(
                            key: _formKey,
                            child: Column(
                              children: [
                                if (_register) ...[
                                  TextFormField(
                                    controller: _nameController,
                                    textInputAction: TextInputAction.next,
                                    decoration: const InputDecoration(
                                      labelText: 'Имя',
                                      prefixIcon: Icon(Icons.person_outline),
                                    ),
                                    validator: (value) => value == null || value.trim().length < 2
                                        ? 'Введите имя'
                                        : null,
                                  ),
                                  const SizedBox(height: 14),
                                ],
                                TextFormField(
                                  controller: _emailController,
                                  keyboardType: TextInputType.emailAddress,
                                  textInputAction: TextInputAction.next,
                                  decoration: const InputDecoration(
                                    labelText: 'Email',
                                    prefixIcon: Icon(Icons.alternate_email),
                                  ),
                                  validator: (value) {
                                    final text = value?.trim() ?? '';
                                    return text.contains('@') ? null : 'Введите корректный email';
                                  },
                                ),
                                if (_register) ...[
                                  const SizedBox(height: 14),
                                  TextFormField(
                                    controller: _phoneController,
                                    keyboardType: TextInputType.phone,
                                    textInputAction: TextInputAction.next,
                                    decoration: const InputDecoration(
                                      labelText: 'Телефон, необязательно',
                                      prefixIcon: Icon(Icons.phone_outlined),
                                    ),
                                  ),
                                ],
                                const SizedBox(height: 14),
                                TextFormField(
                                  controller: _passwordController,
                                  obscureText: _obscure,
                                  textInputAction: TextInputAction.done,
                                  onFieldSubmitted: (_) => _submit(),
                                  decoration: InputDecoration(
                                    labelText: 'Пароль',
                                    prefixIcon: const Icon(Icons.lock_outline),
                                    suffixIcon: IconButton(
                                      onPressed: () => setState(() => _obscure = !_obscure),
                                      icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                                    ),
                                  ),
                                  validator: (value) => (value?.length ?? 0) < 8
                                      ? 'Пароль должен содержать не менее 8 символов'
                                      : null,
                                ),
                                if (_register) ...[
                                  const SizedBox(height: 10),
                                  CheckboxListTile(
                                    contentPadding: EdgeInsets.zero,
                                    value: _consent,
                                    onChanged: (value) => setState(() => _consent = value ?? false),
                                    title: const Text(
                                      'Согласен с обработкой персональных данных и правилами сервиса',
                                      style: TextStyle(fontSize: 13),
                                    ),
                                    controlAffinity: ListTileControlAffinity.leading,
                                  ),
                                ],
                                const SizedBox(height: 16),
                                FilledButton(
                                  onPressed: session.busy ? null : _submit,
                                  child: session.busy
                                      ? const SizedBox(
                                          width: 22,
                                          height: 22,
                                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                        )
                                      : Text(_register ? 'Зарегистрироваться' : 'Войти'),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextButton(
                        onPressed: session.busy
                            ? null
                            : () {
                                session.clearError();
                                setState(() => _register = !_register);
                              },
                        child: Text(
                          _register
                              ? 'Уже есть аккаунт? Войти'
                              : 'Нет аккаунта? Зарегистрироваться',
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}
