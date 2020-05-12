registry = []


def register(func):
    """

    函数装饰器在运行时立即执行
    """
    print('running register(%s)' % func)
    registry.append(func)
    return func


@register
def f1():
    print('running f1()')

@register
def f2():
    print('running f2()')
def f3():
    print('running f3()')


if __name__ == '__main__':
    print('registry->', registry)
    f1()
    f2()
    f3()
    print(registry)
