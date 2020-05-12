# def decorate(func):
#     print("Begin")
#     func()
#     print("End")
# def target():
#     print("running target()")
# decorate(target)
# # equal below function
# @decorate
# def target():
#     print("running target()")

# from functools import wraps
# def testControl(par):
#     def de(func):
#         @wraps(func)
#         def return_w(flag):
#             if flag == 1:
#                 pass
#             else:
#                 print(par)
#                 func()
#                 print("after")
#         return return_w
#     return de
#
# @testControl("sss")
# def t():
#     print("main")


def log_time(func):
    def make_decorater(*args, **kwargs):  # 接受调用语句的实参，在下面传递给被装饰函数（原函数）
        print('现在开始装饰')
        test_func = func(*args, **kwargs)  # 如果在这里return，则下面的代码无法执行，所以引用并在下面返回
        print(test_func)
        print('现在结束装饰')
        return test_func  # 因为被装饰函数里有return，所以需要给调用语句（test（2））一个返回，又因为test_func = func(*args,**kwargs)已经调用了被装饰函数，这里就不用带（）调用了，区别在于运行顺序的不同。

    return make_decorater


@log_time
def test(num):
    print('我是被装饰的函数')
    return num + 1


test(2)  # test(2)=make_decorater(2)


