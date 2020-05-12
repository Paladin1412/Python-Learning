from itertools import chain

my_list = [1, 2, 3]
my_dict = {
    "a": "a1",
    "b": "b1"
}


# for value in chain(my_list,my_dict,range(5,10)):
#     print(value)

# yield from iterable
# def my_chain(*args,**kwargs):
#     for my_iterable in args:
#         yield from my_iterable
# for value in my_chain(my_list,my_dict,range(5,10)):
#     print(value)

def g1(iterable):
    yield iterable


def g2(iterable):
    yield from iterable


# for v in g1(range(10)):
#     print(v)
# for v in g2(range(10)):
#     print(v)
# yield from会在调用方与子生成器之间建立一个双向通道

# demo
# 期望输出：{"面膜":（5700,[1200,1500,3000]),"手机":(289,[28,55,98,108]), "大衣":(1688,[280,560,778,70])}
final_ret={}
def middle(key):
    pass
def main():
    data_sets={
        "面膜":[1200,1500,3000],
        "手机":[28,55,98,108],
        "大衣":[280,560,778,70]
    }
